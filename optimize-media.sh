#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "$0")"

marker="/var/www/html/wp-content/uploads/.itsoft-webp-v2"
force="${1:-}"

if [[ "$force" != "--force" ]] && docker compose exec -T wordpress test -f "$marker"; then
  echo "Media is already optimized. Use --force to regenerate it again."
  exit 0
fi

echo "==> Regenerating uploaded media as optimized WebP sizes"
docker compose exec -T wordpress wp media regenerate --yes --allow-root

echo "==> Clearing WordPress caches"
docker compose exec -T wordpress wp cache flush --allow-root

docker compose exec -T wordpress touch "$marker"

echo "Media optimization complete. Original uploads were preserved."
