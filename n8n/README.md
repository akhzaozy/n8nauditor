# SentinelAI - n8n Automation Workflows

This directory contains pre-configured automation pipelines for **SentinelAI**.

## Workflows Included

### `sentinel_audit_pipeline.json`
Automates the end-to-end security cycle:
1. **Schedule Trigger**: Fires every 6 hours automatically.
2. **Webhook Trigger (`POST /webhook/sentinel-audit`)**: Accepts audits from local/remote audit agents or manual dashboard clicks.
3. **Parse & Validate JSON**: Validates incoming server telemetry.
4. **AI Risk Analysis**: Dispatches findings to configured OpenAI-compatible gateway (`9router`, `Ollama`, or `OpenAI`) with a strict read-only security prompt.
5. **Merge & Save**: Transmits enriched audit records to SentinelAI `/api/v1/audits/ingest`.
6. **Alert Notification**: Filters for `HIGH` or `CRITICAL` risk scores and notifies Discord/Telegram/Webhook.

## How to Import into n8n

1. Open n8n web interface at `http://YOUR_SERVER_IP:5678`.
2. Go to **Workflows** → **Add Workflow** (or press `Ctrl+O`).
3. Click the top right **... (More options)** menu → **Import from File**.
4. Select `n8n/workflows/sentinel_audit_pipeline.json`.
5. Under environment or credentials, ensure your `AI_API_KEY` and `SENTINEL_API_SECRET` match your `.env` configuration.
6. Toggle the workflow to **Active**.
