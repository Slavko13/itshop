#!/usr/bin/env bash
set -Eeuo pipefail

fail() {
  printf 'ERROR: %s\n' "$*" >&2
  exit 1
}

usage() {
  printf 'Usage: bash enable-https.sh test.example.com\n' >&2
}

set_env_value() {
  local key="$1"
  local value="$2"
  local temporary
  temporary="$(mktemp)"

  awk -v key="$key" -v value="$value" '
    BEGIN { found = 0 }
    index($0, key "=") == 1 {
      print key "=" value
      found = 1
      next
    }
    { print }
    END {
      if (!found) {
        print key "=" value
      }
    }
  ' .env > "$temporary"
  cat "$temporary" > .env
  rm -f "$temporary"
  chmod 600 .env
}

[[ $# -eq 1 ]] || { usage; exit 2; }
[[ -f .env ]] || fail '.env not found. Run server-bootstrap.sh first.'
command -v docker >/dev/null 2>&1 || fail 'Docker is not installed.'
command -v curl >/dev/null 2>&1 || fail 'curl is not installed.'

domain="${1#https://}"
domain="${domain%/}"
domain="${domain,,}"
if [[ ! "$domain" =~ ^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$ ]]; then
  fail "Invalid public domain: $domain"
fi

set -a
# shellcheck disable=SC1091
source .env
set +a
old_url="${ITSOFT_LOCAL_URL:-http://localhost:8080}"
new_url="https://$domain"

cp -p .env ".env.pre-https.$(date -u +%Y%m%dT%H%M%SZ)"
set_env_value ITSOFT_DOMAIN "$domain"
set_env_value ITSOFT_LOCAL_URL "$new_url"
set_env_value ITSOFT_HTTP_BIND 127.0.0.1

docker compose --profile https up -d --build --force-recreate wordpress caddy

wordpress_ready=0
for _ in $(seq 1 90); do
  if docker compose exec -T wordpress curl --fail --silent http://127.0.0.1/wp-login.php >/dev/null 2>&1; then
    wordpress_ready=1
    break
  fi
  sleep 2
done
[[ "$wordpress_ready" -eq 1 ]] || fail 'WordPress did not become ready.'

if [[ "$old_url" != "$new_url" ]]; then
  docker compose exec -T wordpress wp search-replace \
    "$old_url" "$new_url" \
    --all-tables --skip-columns=guid --precise --allow-root

  old_scheme_relative="${old_url#http:}"
  old_scheme_relative="${old_scheme_relative#https:}"
  new_scheme_relative="//$domain"
  if [[ -n "$old_scheme_relative" && "$old_scheme_relative" != "$new_scheme_relative" ]]; then
    docker compose exec -T wordpress wp search-replace \
      "$old_scheme_relative" "$new_scheme_relative" \
      --all-tables --skip-columns=guid --precise --allow-root
  fi
fi

docker compose exec -T wordpress wp eval \
  'global $wpdb; $wpdb->delete( $wpdb->prefix . "nextend2_section_storage", array( "type" => "cache" ), array( "%s" ) );' \
  --allow-root >/dev/null 2>&1 || true
docker compose exec -T wordpress wp cache flush --allow-root >/dev/null 2>&1 || true

if ! curl --fail --silent --show-error --head --retry 12 --retry-all-errors --retry-delay 5 "$new_url" >/dev/null; then
  docker compose --profile https logs --no-color --tail=80 caddy >&2 || true
  fail "HTTPS check failed. Verify the A record for $domain and inbound TCP ports 80/443."
fi

docker compose --profile https ps
printf '\nHTTPS is ready: %s\n' "$new_url"
printf 'Previous environment settings: .env.pre-https.*\n'
