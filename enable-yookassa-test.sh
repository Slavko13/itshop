#!/usr/bin/env bash
set -Eeuo pipefail

fail() {
  printf 'ERROR: %s\n' "$*" >&2
  exit 1
}

set_env_value() {
  local key="$1"
  local value="$2"
  local temporary
  temporary="$(mktemp)"
  awk -v key="$key" -v value="$value" '
    BEGIN { found = 0 }
    index($0, key "=") == 1 { print key "=" value; found = 1; next }
    { print }
    END { if (!found) print key "=" value }
  ' .env > "$temporary"
  cat "$temporary" > .env
  rm -f "$temporary"
  chmod 600 .env
}

[[ -f .env ]] || fail '.env not found.'
set -a
# shellcheck disable=SC1091
source .env
set +a
[[ "${ITSOFT_LOCAL_URL:-}" == https://* ]] || fail 'Enable HTTPS first with enable-https.sh.'

cp -p .env ".env.pre-yookassa.$(date -u +%Y%m%dT%H%M%SZ)"
set_env_value ITSOFT_PAYMENT_INTEGRATIONS_ENABLED 1
export ITSOFT_PAYMENT_INTEGRATIONS_ENABLED=1
docker compose --profile https up -d --force-recreate wordpress caddy

if docker compose exec -T wordpress wp plugin is-installed yookassa --allow-root >/dev/null 2>&1; then
  docker compose exec -T wordpress wp plugin activate yookassa --allow-root
else
  docker compose exec -T wordpress wp plugin install yookassa --activate --allow-root
fi

docker compose exec -T wordpress wp cache flush --allow-root >/dev/null 2>&1 || true
printf '\nYooKassa test integration is enabled and the official plugin is active.\n'
printf 'Open: %s/wp-admin/admin.php?page=wc-settings&tab=checkout\n' "$ITSOFT_LOCAL_URL"
printf 'In WooCommerce -> YooKassa settings, connect the TEST store.\n'
printf 'If the plugin asks for ShopID/secret, use only test credentials. Never paste the secret into chat or Git.\n'
