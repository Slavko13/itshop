#!/usr/bin/env bash
set -Eeuo pipefail

fail() {
  printf 'ERROR: %s\n' "$*" >&2
  exit 1
}

usage() {
  cat <<'EOF'
Usage:
  ITSOFT_SITE_URL=http://SERVER_IP:8080 ./server-bootstrap.sh /path/to/itsoft-full-*.tar.gz

The backup must be transferred to the server separately and must never be committed to Git.
EOF
}

[[ $# -eq 1 ]] || { usage; exit 2; }

backup_archive="$1"
site_url="${ITSOFT_SITE_URL:-http://localhost:8080}"
project_name="${ITSOFT_COMPOSE_PROJECT_NAME:-itsoft-test}"

[[ -f "$backup_archive" ]] || fail "Backup archive not found: $backup_archive"
[[ "$site_url" =~ ^https?://[^[:space:]]+$ ]] || fail "ITSOFT_SITE_URL must be an absolute http(s) URL"
command -v docker >/dev/null 2>&1 || fail "Docker is not installed"
docker compose version >/dev/null 2>&1 || fail "Docker Compose plugin is not installed"
command -v tar >/dev/null 2>&1 || fail "tar is not installed"
command -v sha256sum >/dev/null 2>&1 || fail "sha256sum is not installed"

if [[ ! -f .env ]]; then
  command -v openssl >/dev/null 2>&1 || fail "openssl is required to generate test-server secrets"
  db_password="$(openssl rand -hex 24)"
  db_root_password="$(openssl rand -hex 24)"
  wp_salt="$(openssl rand -hex 48)"

  umask 077
  cat > .env <<EOF
ITSOFT_COMPOSE_PROJECT_NAME=$project_name
ITSOFT_HTTP_PORT=${ITSOFT_HTTP_PORT:-8080}
ITSOFT_MAILPIT_PORT=${ITSOFT_MAILPIT_PORT:-8025}
ITSOFT_LOCAL_URL=$site_url
ITSOFT_WP_ENVIRONMENT_TYPE=staging
ITSOFT_EXTERNAL_INTEGRATIONS_DISABLED=1
ITSOFT_WP_SALT=$wp_salt
ITSOFT_LICENSE_ENCRYPTION_KEY=AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
ITSOFT_DB_NAME=itsoft_test
ITSOFT_DB_USER=itsoft
ITSOFT_DB_PASSWORD=$db_password
ITSOFT_DB_ROOT_PASSWORD=$db_root_password
EOF
  chmod 600 .env
else
  printf 'Using existing .env.\n'
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

project_name="${ITSOFT_COMPOSE_PROJECT_NAME:-itsoft-test}"
app_volume="${project_name}_app_code"
uploads_volume="${project_name}_local_uploads"
db_volume="${project_name}_db_data"

work_dir="$(mktemp -d -t itsoft-restore.XXXXXXXX)"
cleanup() {
  rm -rf "$work_dir"
}
trap cleanup EXIT

tar -xzf "$backup_archive" -C "$work_dir"
for required in manifest.json SHA256SUMS database.sql.gz app-code.tar.gz uploads.tar.gz source-url.txt; do
  [[ -f "$work_dir/$required" ]] || fail "Backup is incomplete: $required is missing"
done
sed -i 's/\r$//' "$work_dir/SHA256SUMS"
(
  cd "$work_dir"
  sha256sum -c SHA256SUMS
)

docker_work_dir="$work_dir"
if [[ -n "${MSYSTEM:-}" ]] && command -v cygpath >/dev/null 2>&1; then
  docker_work_dir="$(cygpath -w "$work_dir")"
  export MSYS_NO_PATHCONV=1
fi

for volume in "$app_volume" "$uploads_volume" "$db_volume"; do
  docker volume inspect "$volume" >/dev/null 2>&1 || docker volume create "$volume" >/dev/null
done

volume_is_empty() {
  local volume="$1"
  docker run --rm --mount "type=volume,source=$volume,target=/data" alpine:3.20 \
    sh -c '[ -z "$(find /data -mindepth 1 -maxdepth 1 -print -quit)" ]'
}

volume_is_empty "$app_volume" || fail "Volume is not empty: $app_volume. Refusing to overwrite existing data."
volume_is_empty "$uploads_volume" || fail "Volume is not empty: $uploads_volume. Refusing to overwrite existing data."
volume_is_empty "$db_volume" || fail "Volume is not empty: $db_volume. Refusing to overwrite existing data."

docker run --rm \
  --mount "type=volume,source=$app_volume,target=/target" \
  --mount "type=bind,source=$docker_work_dir,target=/backup,readonly" \
  alpine:3.20 tar -xzf /backup/app-code.tar.gz -C /target

docker run --rm \
  --mount "type=volume,source=$uploads_volume,target=/target" \
  --mount "type=bind,source=$docker_work_dir,target=/backup,readonly" \
  alpine:3.20 tar -xzf /backup/uploads.tar.gz -C /target

docker compose up -d db

db_ready=0
for _ in $(seq 1 60); do
  if docker compose exec -T db healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1; then
    db_ready=1
    break
  fi
  sleep 2
done
[[ "$db_ready" -eq 1 ]] || fail "MariaDB did not become ready"

db_container="$(docker compose ps -q db)"
[[ -n "$db_container" ]] || fail "Database container is not running"
docker cp "$work_dir/database.sql.gz" "$db_container:/tmp/itsoft-restore.sql.gz"
docker compose exec -T db sh -lc \
  'gzip -dc /tmp/itsoft-restore.sql.gz | mariadb --user=root --password="$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'
docker compose exec -T db rm -f /tmp/itsoft-restore.sql.gz

docker compose up -d --build

wordpress_ready=0
for _ in $(seq 1 90); do
  if docker compose exec -T wordpress curl --fail --silent http://127.0.0.1/wp-login.php >/dev/null 2>&1; then
    wordpress_ready=1
    break
  fi
  sleep 2
done
[[ "$wordpress_ready" -eq 1 ]] || fail "WordPress did not become ready"

source_url="$(tr -d '\r\n' < "$work_dir/source-url.txt")"
if [[ -n "$source_url" && "$source_url" != "$ITSOFT_LOCAL_URL" ]]; then
  docker compose exec -T wordpress wp search-replace \
    "$source_url" "$ITSOFT_LOCAL_URL" \
    --all-tables --skip-columns=guid --precise --allow-root
fi

docker compose exec -T wordpress wp cache flush --allow-root >/dev/null 2>&1 || true
docker compose ps

cat <<EOF

ITSOFT test server is ready.
Site: $ITSOFT_LOCAL_URL
Mailpit is bound to server localhost:${ITSOFT_MAILPIT_PORT:-8025}.
External payment, Telegram, Jivo and SMTP calls are disabled.
EOF
