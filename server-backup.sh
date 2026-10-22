#!/usr/bin/env bash
set -Eeuo pipefail

output_dir="${1:-.backups}"
mkdir -p "$output_dir"
chmod 700 "$output_dir"

command -v docker >/dev/null 2>&1 || { printf 'Docker is required.\n' >&2; exit 1; }
docker compose version >/dev/null 2>&1 || { printf 'Docker Compose is required.\n' >&2; exit 1; }

set -a
# shellcheck disable=SC1091
source .env
set +a

project_name="${ITSOFT_COMPOSE_PROJECT_NAME:-itsoft-test}"
app_volume="${project_name}_app_code"
uploads_volume="${project_name}_local_uploads"
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
work_dir="$(mktemp -d -p "$output_dir" ".stage-$timestamp.XXXXXXXX")"
archive="$output_dir/itsoft-full-$timestamp.tar.gz"
maintenance_enabled=0

cleanup() {
  if [[ "$maintenance_enabled" -eq 1 ]]; then
    docker compose exec -T wordpress wp maintenance-mode deactivate --allow-root >/dev/null 2>&1 || true
  fi
  rm -rf "$work_dir"
}
trap cleanup EXIT

docker compose up -d db wordpress
docker volume inspect "$app_volume" >/dev/null
docker volume inspect "$uploads_volume" >/dev/null

if docker compose exec -T wordpress wp maintenance-mode activate --allow-root >/dev/null 2>&1; then
  maintenance_enabled=1
fi

db_container="$(docker compose ps -q db)"
[[ -n "$db_container" ]] || { printf 'Database container is not running.\n' >&2; exit 1; }
remote_dump="/tmp/itsoft-full-$timestamp.sql.gz"
docker compose exec -T db sh -lc \
  "mariadb-dump --user=root --password=\"\$MARIADB_ROOT_PASSWORD\" --single-transaction --quick --routines --triggers --events --add-drop-table \"\$MARIADB_DATABASE\" | gzip -c > '$remote_dump'"
docker cp "$db_container:$remote_dump" "$work_dir/database.sql.gz"
docker compose exec -T db rm -f "$remote_dump"

absolute_work_dir="$(cd "$work_dir" && pwd)"
if [[ -n "${MSYSTEM:-}" ]] && command -v cygpath >/dev/null 2>&1; then
  absolute_work_dir="$(cygpath -w "$absolute_work_dir")"
  export MSYS_NO_PATHCONV=1
fi
docker run --rm \
  --mount "type=volume,source=$app_volume,target=/source,readonly" \
  --mount "type=bind,source=$absolute_work_dir,target=/backup" \
  alpine:3.20 tar -czf /backup/app-code.tar.gz --exclude=./.maintenance -C /source .

docker run --rm \
  --mount "type=volume,source=$uploads_volume,target=/source,readonly" \
  --mount "type=bind,source=$absolute_work_dir,target=/backup" \
  alpine:3.20 tar -czf /backup/uploads.tar.gz -C /source .

printf '%s' "${ITSOFT_LOCAL_URL:-http://localhost:8080}" > "$work_dir/source-url.txt"
git_commit="$(git rev-parse HEAD 2>/dev/null || printf unknown)"

cat > "$work_dir/manifest.json" <<EOF
{
  "format": "itsoft-full-backup-v1",
  "createdAtUtc": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "gitCommit": "$git_commit",
  "composeProject": "$project_name",
  "sourceUrl": "${ITSOFT_LOCAL_URL:-http://localhost:8080}",
  "contains": ["database", "wordpress-app-code", "uploads"],
  "warning": "Contains private store data. Keep encrypted in transit and restrict access."
}
EOF

(
  cd "$work_dir"
  sha256sum database.sql.gz app-code.tar.gz uploads.tar.gz source-url.txt > SHA256SUMS
)

tar -czf "$archive" -C "$work_dir" .
sha256sum "$archive" > "$archive.sha256"
chmod 600 "$archive" "$archive.sha256"

printf 'FULL_BACKUP=%s\n' "$archive"
printf 'WARNING: contains database records, users, orders, license inventory and uploads.\n'
