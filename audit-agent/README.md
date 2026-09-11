# SentinelAI Audit Agent (Collector)

The **SentinelAI Audit Agent** is a lightweight, strictly read-only inspection script designed to gather Linux server telemetry and identify misconfigurations without perturbing production workloads.

## Security & Safety Principles

- **Strictly Read-Only**: Performs zero configuration changes, zero package installations, zero service restarts, and zero writes to system directories.
- **Zero Shell Access for AI**: AI models never connect to the host shell. The collector produces structured JSON telemetry that is safely forwarded to n8n and the SentinelAI API.
- **Data Scrubbing**: Sensitive files (such as `/etc/shadow` contents, SSH private keys, environment secrets, and passwords) are **never** opened, read, or transmitted. Only safe metadata and permission octals (e.g. `0640`) are checked.
- **Low Overhead**: Safe execution wrappers with timeouts guarantee zero hanging or resource starvation on production servers.

## Collector Implementations

Two implementations are provided with 100% identical JSON schemas:
1. `sentinel-collector.sh`: Pure POSIX/Bash with zero external dependencies.
2. `sentinel-collector.py`: Pure Python 3 using standard libraries only (`urllib`, `subprocess`, `json`, `os`).

## Usage Instructions

### 1. Manual / Dry-Run (View Telemetry)
```bash
./sentinel-collector.sh --dry-run
# or with Python
python3 sentinel-collector.py --dry-run
```

### 2. Output to File
```bash
./sentinel-collector.sh -o /tmp/sentinel-report.json
```

### 3. Send Directly to SentinelAI / n8n Webhook
```bash
./sentinel-collector.sh \
  --send-to "http://YOUR_SERVER_IP:8080/api/v1/audits/ingest" \
  --api-token "your-sentinel-secret-token"
```

Or send to n8n webhook:
```bash
./sentinel-collector.sh \
  --send-to "http://YOUR_SERVER_IP:5678/webhook/sentinel-audit"
```

## Scheduled Execution (Cron)

To automate audits every 6 hours on the target server, add the following entry to `/etc/crontab` or `crontab -e`:

```bash
# Run SentinelAI audit every 6 hours at minute 0
0 */6 * * * root /opt/sentinel-ai/audit-agent/sentinel-collector.sh --send-to "http://localhost:8080/api/v1/audits/ingest" --api-token "sentinel-secret-token-change-me" >/dev/null 2>&1
```
