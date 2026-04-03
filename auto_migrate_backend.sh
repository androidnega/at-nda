#!/usr/bin/env bash
set -euo pipefail

# Backend-only migration runner for live server.
# Usage (after `git pull` in this repo):
#   ./auto_migrate_backend.sh
#
# Notes:
# - Uses `php artisan migrate --force --no-interaction` so it won't prompt on prod.
# - Idempotent: Laravel will only apply new migrations.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

if [[ ! -f "artisan" ]]; then
  echo "Error: artisan not found in $ROOT_DIR" >&2
  exit 1
fi

echo "Running backend migrations from: $ROOT_DIR"
php artisan migrate --force --no-interaction
echo "Backend migrations completed."

