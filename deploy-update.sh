#!/usr/bin/env bash
set -Eeuo pipefail

fail() {
  printf 'ERROR: %s\n' "$*" >&2
  exit 1
}

cd "$(dirname "$0")"

[[ -f .env ]] || fail '.env not found. Run the initial server installation first.'
command -v docker >/dev/null 2>&1 || fail 'Docker is not installed.'
docker compose version >/dev/null 2>&1 || fail 'Docker Compose plugin is not installed.'

set -a
# shellcheck disable=SC1091
source .env
set +a

if [[ -n "${ITSOFT_DOMAIN:-}" ]]; then
  echo "==> Rebuilding WordPress and reloading Caddy"
  docker compose --profile https up -d --build --force-recreate wordpress caddy
else
  echo "==> Rebuilding WordPress"
  docker compose up -d --build --force-recreate wordpress
fi

echo "==> Waiting for WordPress"
wordpress_ready=0
for _ in $(seq 1 60); do
  if docker compose exec -T wordpress curl --fail --silent http://127.0.0.1/wp-login.php >/dev/null 2>&1; then
    wordpress_ready=1
    break
  fi
  sleep 2
done
[[ "$wordpress_ready" -eq 1 ]] || fail 'WordPress did not become ready.'

bash optimize-media.sh
docker compose exec -T wordpress wp cache flush --allow-root >/dev/null 2>&1 || true

if [[ -n "${ITSOFT_DOMAIN:-}" ]]; then
  site_url="https://${ITSOFT_DOMAIN}"
else
  site_url="${ITSOFT_LOCAL_URL:-http://localhost:8080}"
fi

curl --fail --silent --show-error --head "$site_url" >/dev/null \
  || fail "Site health check failed: $site_url"

docker compose ${ITSOFT_DOMAIN:+--profile https} ps
printf '\nUpdate applied successfully.\nSite: %s\nCommit: %s\n' \
  "$site_url" "$(git rev-parse --short HEAD 2>/dev/null || printf unknown)"
