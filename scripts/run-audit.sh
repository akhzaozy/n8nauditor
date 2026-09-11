#!/usr/bin/env bash
# =============================================================================
# SentinelAI - Manual Audit Trigger Helper
# =============================================================================
set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET_API="${1:-http://localhost:8080/api/v1/audits/ingest}"
SECRET_TOKEN="${2:-sentinel-secret-token-change-me}"

# If .env exists, extract SENTINEL_API_SECRET if not overridden
if [ -f "$DIR/.env" ] && [ -z "${2:-}" ]; then
  ENV_TOKEN=$(grep -E '^SENTINEL_API_SECRET=' "$DIR/.env" | cut -d= -f2- | tr -d ' "' || true)
  [ -n "$ENV_TOKEN" ] && SECRET_TOKEN="$ENV_TOKEN"
fi

echo "Triggering SentinelAI Collector..."
echo "Destination: $TARGET_API"

"$DIR/audit-agent/sentinel-collector.sh" \
  --send-to "$TARGET_API" \
  --api-token "$SECRET_TOKEN"
