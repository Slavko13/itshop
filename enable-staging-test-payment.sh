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

[[ -f .env ]] || fail '.env not found. Run server-bootstrap.sh first.'
set -a
# shellcheck disable=SC1091
source .env
set +a

[[ "${ITSOFT_WP_ENVIRONMENT_TYPE:-}" == "staging" ]] || fail 'Mock payment can be enabled by this script only on staging.'
[[ "${ITSOFT_LOCAL_URL:-}" == https://* ]] || fail 'Enable HTTPS first with enable-https.sh.'

cp -p .env ".env.pre-mock-payment.$(date -u +%Y%m%dT%H%M%SZ)"
set_env_value ITSOFT_MOCK_PAYMENT_ENABLED 1

docker compose --profile https up -d --build --force-recreate wordpress caddy
docker compose exec -T wordpress wp cache flush --allow-root >/dev/null 2>&1 || true

docker compose exec -T wordpress wp eval '
if ( ! class_exists( "ITSoft_LF_Mock_Payment" ) || ! ITSoft_LF_Mock_Payment::is_enabled() ) {
    WP_CLI::error( "Staging mock payment did not become active." );
}
$gateways = WC()->payment_gateways()->payment_gateways();
if ( empty( $gateways["itsoft_lf_mock"] ) || ! $gateways["itsoft_lf_mock"]->is_available() ) {
    WP_CLI::error( "Staging mock gateway is registered but unavailable." );
}
WP_CLI::success( "Staging test payment is enabled. No money will be charged." );
' --allow-root

printf '\nWARNING: keep only test inventory on this staging server.\n'
printf 'Checkout: %s/checkout/\n' "${ITSOFT_LOCAL_URL%/}"
