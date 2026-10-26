#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "$0")"

echo "==> Regenerating uploaded media as optimized WebP sizes"
docker compose exec -T wordpress wp media regenerate --yes --allow-root

echo "==> Clearing WordPress caches"
docker compose exec -T wordpress wp cache flush --allow-root

echo "Media optimization complete. Original uploads were preserved."
