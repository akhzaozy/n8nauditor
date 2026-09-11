#!/usr/bin/env bash
# =============================================================================
# SentinelAI - Bootstrap & Initialization Helper
# =============================================================================
set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$DIR"

echo "=== [1/4] Preparing Environment File ==="
if [ ! -f .env ]; then
  cp .env.example .env
  echo "Created .env from .env.example"
else
  echo ".env already exists."
fi

echo "=== [2/4] Setting File Permissions ==="
mkdir -p app/storage/framework/views app/storage/framework/sessions app/storage/framework/cache app/storage/logs app/bootstrap/cache
chmod -R 775 app/storage app/bootstrap/cache 2>/dev/null || true

echo "=== [3/4] Ensuring Collectors are Executable ==="
chmod +x audit-agent/sentinel-collector.sh audit-agent/sentinel-collector.py scripts/*.sh 2>/dev/null || true

echo "=== [4/4] Done! ==="
echo "You can now start SentinelAI with:"
echo "  docker compose up -d --build"
echo ""
echo "After containers start, run database migrations:"
echo "  docker compose exec app php artisan migrate:fresh --seed --force"
