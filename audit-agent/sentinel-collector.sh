#!/usr/bin/env bash
# =============================================================================
# SentinelAI - Read-Only Linux Server Security & Telemetry Collector
# =============================================================================
# Purpose: Non-intrusive, read-only security audit of Linux hosts.
# Safety:  Zero mutation, zero file modification, zero service restart.
# Output:  Strictly sanitized JSON containing system telemetry & rule findings.
# =============================================================================

set -u

VERSION="1.0.0"
OUTPUT_FILE=""
SEND_TO_URL=""
API_TOKEN=""
DRY_RUN=0

# Parse arguments
while [[ $# -gt 0 ]]; do
  case "$1" in
    --dry-run)
      DRY_RUN=1
      shift
      ;;
    -o|--output)
      OUTPUT_FILE="$2"
      shift 2
      ;;
    -s|--send-to)
      SEND_TO_URL="$2"
      shift 2
      ;;
    -t|--api-token)
      API_TOKEN="$2"
      shift 2
      ;;
    -h|--help)
      echo "SentinelAI Collector v$VERSION"
      echo "Usage: $0 [OPTIONS]"
      echo "  --dry-run              Print JSON to stdout"
      echo "  -o, --output <file>    Save JSON to file"
      echo "  -s, --send-to <url>    POST JSON to SentinelAI / n8n webhook"
      echo "  -t, --api-token <tok>  Set X-Sentinel-Token header"
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      exit 1
      ;;
  esac
done

# Safe command runner with timeout (3s) to prevent hanging
safe_exec() {
  if command -v timeout >/dev/null 2>&1; then
    timeout 3 "$@" 2>/dev/null || true
  else
    "$@" 2>/dev/null || true
  fi
}

# -----------------------------------------------------------------------------
# 1. SYSTEM TELEMETRY
# -----------------------------------------------------------------------------
HOSTNAME=$(hostname 2>/dev/null || echo "unknown-host")
KERNEL=$(uname -r 2>/dev/null || echo "unknown-kernel")
ARCH=$(uname -m 2>/dev/null || echo "unknown-arch")

OS_NAME="Linux"
OS_VERSION=""
if [ -f /etc/os-release ]; then
  OS_NAME=$(grep -E '^NAME=' /etc/os-release | cut -d= -f2 | tr -d '"' 2>/dev/null || echo "Linux")
  OS_VERSION=$(grep -E '^VERSION_ID=' /etc/os-release | cut -d= -f2 | tr -d '"' 2>/dev/null || echo "")
fi

# Uptime
UPTIME_STR=$(uptime -p 2>/dev/null || uptime 2>/dev/null || echo "unknown")
UPTIME_SECONDS=0
if [ -f /proc/uptime ]; then
  UPTIME_SECONDS=$(cut -d. -f1 /proc/uptime 2>/dev/null || echo 0)
fi

# CPU Load & Usage
CPU_CORES=$(grep -c ^processor /proc/cpuinfo 2>/dev/null || echo 1)
[ "$CPU_CORES" -le 0 ] && CPU_CORES=1
LOAD_1M=$(awk '{print $1}' /proc/loadavg 2>/dev/null || echo 0)
LOAD_5M=$(awk '{print $2}' /proc/loadavg 2>/dev/null || echo 0)
LOAD_15M=$(awk '{print $3}' /proc/loadavg 2>/dev/null || echo 0)

# Estimate CPU usage percentage
CPU_USAGE=0
if command -v top >/dev/null 2>&1; then
  CPU_USAGE=$(top -bn1 2>/dev/null | grep -E "Cpu\(s\)" | awk '{print int(100 - $8)}' 2>/dev/null || echo 0)
  [ -z "$CPU_USAGE" ] && CPU_USAGE=0
fi

# Memory
MEM_TOTAL=0
MEM_FREE=0
MEM_AVAILABLE=0
MEM_USAGE_PCT=0
if [ -f /proc/meminfo ]; then
  MEM_TOTAL=$(grep MemTotal /proc/meminfo | awk '{print $2}' 2>/dev/null || echo 0)
  MEM_FREE=$(grep MemFree /proc/meminfo | awk '{print $2}' 2>/dev/null || echo 0)
  MEM_AVAILABLE=$(grep MemAvailable /proc/meminfo | awk '{print $2}' 2>/dev/null || echo 0)
  if [ "$MEM_TOTAL" -gt 0 ] && [ "$MEM_AVAILABLE" -gt 0 ]; then
    MEM_USAGE_PCT=$(( (MEM_TOTAL - MEM_AVAILABLE) * 100 / MEM_TOTAL ))
  fi
fi

# Root Disk Usage
DISK_TOTAL_MB=0
DISK_USED_MB=0
DISK_USAGE_PCT=0
if command -v df >/dev/null 2>&1; then
  DISK_INFO=$(df -m / 2>/dev/null | tail -1)
  DISK_TOTAL_MB=$(echo "$DISK_INFO" | awk '{print $2}' 2>/dev/null || echo 0)
  DISK_USED_MB=$(echo "$DISK_INFO" | awk '{print $3}' 2>/dev/null || echo 0)
  DISK_USAGE_PCT=$(echo "$DISK_INFO" | awk '{print $5}' | tr -d '%' 2>/dev/null || echo 0)
fi

# Thermal Temperature (if available)
TEMP_C="null"
if [ -f /sys/class/thermal/thermal_zone0/temp ]; then
  RAW_TEMP=$(cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null || echo 0)
  if [ "$RAW_TEMP" -gt 0 ]; then
    TEMP_C=$(( RAW_TEMP / 1000 ))
  fi
fi

# -----------------------------------------------------------------------------
# 2. SSH AUDIT
# -----------------------------------------------------------------------------
SSH_PERMIT_ROOT="unknown"
SSH_PASSWORD_AUTH="unknown"
SSH_PUBKEY_AUTH="unknown"
SSH_PORT="22"
SSH_CONFIG_VALID=true

# Try sshd -T (non-destructive test mode, checks effective config)
SSHD_TEST_OUT=$(safe_exec sshd -T)
if [ -n "$SSHD_TEST_OUT" ]; then
  [ -n "$(echo "$SSHD_TEST_OUT" | grep -iE '^permitrootlogin no')" ] && SSH_PERMIT_ROOT="no"
  [ -n "$(echo "$SSHD_TEST_OUT" | grep -iE '^permitrootlogin yes')" ] && SSH_PERMIT_ROOT="yes"
  [ -n "$(echo "$SSHD_TEST_OUT" | grep -iE '^permitrootlogin prohibit-password')" ] && SSH_PERMIT_ROOT="prohibit-password"
  [ -n "$(echo "$SSHD_TEST_OUT" | grep -iE '^passwordauthentication yes')" ] && SSH_PASSWORD_AUTH="yes"
  [ -n "$(echo "$SSHD_TEST_OUT" | grep -iE '^passwordauthentication no')" ] && SSH_PASSWORD_AUTH="no"
  [ -n "$(echo "$SSHD_TEST_OUT" | grep -iE '^pubkeyauthentication yes')" ] && SSH_PUBKEY_AUTH="yes"
  [ -n "$(echo "$SSHD_TEST_OUT" | grep -iE '^pubkeyauthentication no')" ] && SSH_PUBKEY_AUTH="no"
  SSH_PORT=$(echo "$SSHD_TEST_OUT" | grep -iE '^port ' | awk '{print $2}' 2>/dev/null || echo "22")
elif [ -f /etc/ssh/sshd_config ]; then
  # Fallback to parsing sshd_config directly
  ROOT_CONF=$(grep -iE '^\s*PermitRootLogin' /etc/ssh/sshd_config | tail -1 | awk '{print $2}')
  [ -n "$ROOT_CONF" ] && SSH_PERMIT_ROOT="$ROOT_CONF"
  PASS_CONF=$(grep -iE '^\s*PasswordAuthentication' /etc/ssh/sshd_config | tail -1 | awk '{print $2}')
  [ -n "$PASS_CONF" ] && SSH_PASSWORD_AUTH="$PASS_CONF"
  PUB_CONF=$(grep -iE '^\s*PubkeyAuthentication' /etc/ssh/sshd_config | tail -1 | awk '{print $2}')
  [ -n "$PUB_CONF" ] && SSH_PUBKEY_AUTH="$PUB_CONF"
  PORT_CONF=$(grep -iE '^\s*Port' /etc/ssh/sshd_config | tail -1 | awk '{print $2}')
  [ -n "$PORT_CONF" ] && SSH_PORT="$PORT_CONF"
fi

# Check configuration syntax validity safely
if command -v sshd >/dev/null 2>&1; then
  safe_exec sshd -t >/dev/null 2>&1 || SSH_CONFIG_VALID=false
fi

# -----------------------------------------------------------------------------
# 3. FIREWALL AUDIT
# -----------------------------------------------------------------------------
FIREWALL_TYPE="none"
FIREWALL_STATUS="inactive"

if command -v ufw >/dev/null 2>&1; then
  FIREWALL_TYPE="ufw"
  UFW_STATUS=$(safe_exec ufw status 2>/dev/null || echo "")
  if echo "$UFW_STATUS" | grep -qi "Status: active"; then
    FIREWALL_STATUS="active"
  fi
elif command -v firewall-cmd >/dev/null 2>&1; then
  FIREWALL_TYPE="firewalld"
  if safe_exec firewall-cmd --state 2>/dev/null | grep -qi "running"; then
    FIREWALL_STATUS="active"
  fi
elif command -v nft >/dev/null 2>&1; then
  FIREWALL_TYPE="nftables"
  NFT_RULES=$(safe_exec nft list ruleset 2>/dev/null || echo "")
  if [ -n "$NFT_RULES" ]; then
    FIREWALL_STATUS="active"
  fi
elif command -v iptables >/dev/null 2>&1; then
  FIREWALL_TYPE="iptables"
  IPT_RULES=$(safe_exec iptables -L -n 2>/dev/null | grep -E '^ACCEPT|^DROP|^REJECT' || echo "")
  if [ -n "$IPT_RULES" ]; then
    FIREWALL_STATUS="active"
  fi
fi

# -----------------------------------------------------------------------------
# 4. NETWORK & LISTENING PORTS
# -----------------------------------------------------------------------------
LISTENING_PORTS_JSON="[]"
if command -v ss >/dev/null 2>&1; then
  RAW_PORTS=$(safe_exec ss -tuln 2>/dev/null | awk 'NR>1 {print $1 "|" $5}')
  PORTS_ARRAY=()
  while IFS='|' read -r proto address; do
    [ -z "$address" ] && continue
    port=$(echo "$address" | awk -F: '{print $NF}')
    ip=$(echo "$address" | sed "s/:$port$//")
    [ -z "$port" ] && continue
    PORTS_ARRAY+=("{\"proto\":\"$proto\",\"ip\":\"$ip\",\"port\":\"$port\"}")
  done <<< "$RAW_PORTS"

  if [ ${#PORTS_ARRAY[@]} -gt 0 ]; then
    # Join with comma
    LISTENING_PORTS_JSON="[$(IFS=,; echo "${PORTS_ARRAY[*]}")]"
  fi
fi

# -----------------------------------------------------------------------------
# 5. USER & PRIVILEGE AUDIT
# -----------------------------------------------------------------------------
TOTAL_USERS=$(wc -l </etc/passwd 2>/dev/null | awk '{print $1}' || echo 0)
UID_ZERO_USERS=()
if [ -f /etc/passwd ]; then
  while read -r u; do
    [ -n "$u" ] && UID_ZERO_USERS+=("$u")
  done < <(awk -F: '($3 == 0) {print $1}' /etc/passwd 2>/dev/null)
fi

SUDO_USERS_COUNT=0
if command -v getent >/dev/null 2>&1; then
  SUDO_GRP=$(safe_exec getent group sudo wheel 2>/dev/null | awk -F: '{print $4}' | tr ',' '\n' | sort -u | grep -v '^$' | wc -l || echo 0)
  SUDO_USERS_COUNT=$SUDO_GRP
fi

# Users with interactive login shells
LOGIN_SHELL_COUNT=$(grep -E -c '(/bin/bash|/bin/sh|/bin/zsh|/bin/dash)$' /etc/passwd 2>/dev/null || echo 0)

# -----------------------------------------------------------------------------
# 6. SYSTEMD SERVICE HEALTH
# -----------------------------------------------------------------------------
FAILED_SERVICES_COUNT=0
FAILED_SERVICES_LIST="[]"
if command -v systemctl >/dev/null 2>&1; then
  FAILED_SVC=$(safe_exec systemctl list-units --state=failed --no-legend --plain 2>/dev/null | awk '{print $1}')
  if [ -n "$FAILED_SVC" ]; then
    SVC_ARRAY=()
    while read -r svc; do
      [ -n "$svc" ] && SVC_ARRAY+=("\"$svc\"")
    done <<< "$FAILED_SVC"
    FAILED_SERVICES_COUNT=${#SVC_ARRAY[@]}
    FAILED_SERVICES_LIST="[$(IFS=,; echo "${SVC_ARRAY[*]}")]"
  fi
fi

# -----------------------------------------------------------------------------
# 7. PACKAGE & SECURITY UPDATES
# -----------------------------------------------------------------------------
PKG_MANAGER="unknown"
UPDATES_AVAILABLE=0
SECURITY_UPDATES=0

if command -v apt-get >/dev/null 2>&1; then
  PKG_MANAGER="apt"
  # Non-intrusive simulated upgrade
  APT_SIM=$(safe_exec apt-get -s upgrade 2>/dev/null || echo "")
  if [ -n "$APT_SIM" ]; then
    UPDATES_AVAILABLE=$(echo "$APT_SIM" | grep -iE '^[0-9]+ upgraded' | awk '{print $1}' 2>/dev/null || echo 0)
    SECURITY_UPDATES=$(echo "$APT_SIM" | grep -iE 'security|esm' | grep -iE 'inst' | wc -l 2>/dev/null || echo 0)
  fi
elif command -v dnf >/dev/null 2>&1; then
  PKG_MANAGER="dnf"
  DNF_SEC=$(safe_exec dnf check-update --security -q 2>/dev/null | grep -v '^$' | wc -l 2>/dev/null || echo 0)
  SECURITY_UPDATES=$DNF_SEC
fi

# -----------------------------------------------------------------------------
# 8. WEB SERVER AUDIT (NGINX / APACHE)
# -----------------------------------------------------------------------------
NGINX_INSTALLED=false
NGINX_VERSION="not-installed"
NGINX_CONFIG_OK="unknown"

if command -v nginx >/dev/null 2>&1; then
  NGINX_INSTALLED=true
  NGINX_VERSION=$(nginx -v 2>&1 | awk -F/ '{print $2}' 2>/dev/null || echo "installed")
  if safe_exec nginx -t -q 2>/dev/null; then
    NGINX_CONFIG_OK="valid"
  else
    NGINX_CONFIG_OK="syntax-error"
  fi
fi

APACHE_INSTALLED=false
APACHE_VERSION="not-installed"
if command -v apache2 >/dev/null 2>&1; then
  APACHE_INSTALLED=true
  APACHE_VERSION=$(apache2 -v 2>&1 | head -1 | awk '{print $3}' 2>/dev/null || echo "installed")
elif command -v httpd >/dev/null 2>&1; then
  APACHE_INSTALLED=true
  APACHE_VERSION=$(httpd -v 2>&1 | head -1 | awk '{print $3}' 2>/dev/null || echo "installed")
fi

# -----------------------------------------------------------------------------
# 9. DOCKER AUDIT (READ-ONLY)
# -----------------------------------------------------------------------------
DOCKER_AVAILABLE=false
DOCKER_VERSION="not-installed"
DOCKER_RUNNING_CONTAINERS=0
DOCKER_PRIVILEGED_COUNT=0

if command -v docker >/dev/null 2>&1; then
  DOCKER_VERSION=$(docker --version 2>/dev/null | awk '{print $3}' | tr -d ',' 2>/dev/null || echo "installed")
  # Check if docker daemon is accessible
  if safe_exec docker info >/dev/null 2>&1; then
    DOCKER_AVAILABLE=true
    DOCKER_RUNNING_CONTAINERS=$(safe_exec docker ps -q 2>/dev/null | wc -l || echo 0)
    # Check for privileged containers
    CONTAINER_IDS=$(safe_exec docker ps -q 2>/dev/null || echo "")
    if [ -n "$CONTAINER_IDS" ]; then
      while read -r cid; do
        [ -z "$cid" ] && continue
        IS_PRIV=$(safe_exec docker inspect --format '{{.HostConfig.Privileged}}' "$cid" 2>/dev/null || echo "false")
        [ "$IS_PRIV" = "true" ] && DOCKER_PRIVILEGED_COUNT=$(( DOCKER_PRIVILEGED_COUNT + 1 ))
      done <<< "$CONTAINER_IDS"
    fi
  fi
fi

# -----------------------------------------------------------------------------
# 10. FILE SYSTEM & SENSITIVE PERMISSION CHECKS (READ-ONLY METADATA ONLY)
# -----------------------------------------------------------------------------
SHADOW_PERM="unknown"
PASSWD_PERM="unknown"
SHADOW_WORLD_READABLE=false

if [ -f /etc/shadow ]; then
  SHADOW_PERM=$(stat -c "%a" /etc/shadow 2>/dev/null || stat -f "%Op" /etc/shadow 2>/dev/null || echo "unknown")
  # Check if world readable (last digit has read bit >= 4)
  LAST_DIGIT="${SHADOW_PERM: -1}"
  if [[ "$LAST_DIGIT" =~ ^[4567]$ ]]; then
    SHADOW_WORLD_READABLE=true
  fi
fi

if [ -f /etc/passwd ]; then
  PASSWD_PERM=$(stat -c "%a" /etc/passwd 2>/dev/null || stat -f "%Op" /etc/passwd 2>/dev/null || echo "unknown")
fi

# -----------------------------------------------------------------------------
# 11. DETERMINISTIC RISK SCORING & FINDINGS GENERATION (RULE ENGINE)
# -----------------------------------------------------------------------------
BASE_SCORE=100
FINDINGS=()

# Helper to append finding
# JSON escape helper
escape_json() {
  echo "$1" | sed 's/\\/\\\\/g; s/"/\\"/g; s/\t/\\t/g; s/\r//g'
}

add_finding() {
  local id="$1"
  local category="$2"
  local severity="$3"
  local impact="$4"
  local title="$5"
  local evidence="$6"
  local desc="$7"
  local rec="$8"

  BASE_SCORE=$(( BASE_SCORE - impact ))

  local item="{"
  item+="\"id\":\"$(escape_json "$id")\","
  item+="\"category\":\"$(escape_json "$category")\","
  item+="\"severity\":\"$(escape_json "$severity")\","
  item+="\"score_impact\":$impact,"
  item+="\"title\":\"$(escape_json "$title")\","
  item+="\"evidence\":\"$(escape_json "$evidence")\","
  item+="\"description\":\"$(escape_json "$desc")\","
  item+="\"recommendation\":\"$(escape_json "$rec")\""
  item+="}"

  FINDINGS+=("$item")
}

# Rule 1: SSH Root Login
if [ "$SSH_PERMIT_ROOT" = "yes" ]; then
  add_finding \
    "SSH-001" "SSH" "HIGH" 15 \
    "Direct SSH root login allowed" \
    "PermitRootLogin yes" \
    "Permitting direct root login allows remote attackers to target the most privileged user account directly." \
    "Set 'PermitRootLogin prohibit-password' or 'no' in /etc/ssh/sshd_config and reload sshd."
fi

# Rule 2: SSH Password Authentication
if [ "$SSH_PASSWORD_AUTH" = "yes" ]; then
  add_finding \
    "SSH-002" "SSH" "HIGH" 10 \
    "SSH password authentication enabled" \
    "PasswordAuthentication yes" \
    "Password-based SSH authentication is susceptible to brute-force and credential stuffing attacks." \
    "Enforce SSH key pair authentication and set 'PasswordAuthentication no' in /etc/ssh/sshd_config."
fi

# Rule 3: SSH Port Default
if [ "$SSH_PORT" = "22" ]; then
  add_finding \
    "SSH-003" "SSH" "LOW" 5 \
    "Default SSH port 22 in use" \
    "Port 22" \
    "Running SSH on default port 22 increases exposure to automated botnet scanners." \
    "Consider changing SSH port to a non-standard high port (e.g. 2222 or higher) or using fail2ban/firewall restrictions."
fi

# Rule 4: Firewall Status
if [ "$FIREWALL_STATUS" != "active" ]; then
  add_finding \
    "FW-001" "FIREWALL" "CRITICAL" 25 \
    "Host firewall is inactive or disabled" \
    "Firewall status: $FIREWALL_STATUS ($FIREWALL_TYPE)" \
    "Without an active firewall, all ports bound to 0.0.0.0 are exposed to the network without perimeter filtering." \
    "Enable and configure ufw ('ufw enable'), firewalld ('systemctl start firewalld'), or nftables immediately."
fi

# Rule 5: Multiple UID 0 accounts
if [ ${#UID_ZERO_USERS[@]} -gt 1 ]; then
  USERS_JOINED=$(IFS=,; echo "${UID_ZERO_USERS[*]}")
  add_finding \
    "USR-001" "USER" "CRITICAL" 30 \
    "Multiple UID 0 superuser accounts detected" \
    "UID 0 accounts: $USERS_JOINED" \
    "Only 'root' should possess UID 0. Extra accounts with UID 0 are a common indicator of compromise or dangerous backdoor." \
    "Audit /etc/passwd, investigate non-root accounts with UID 0, and remove or reassign their UIDs immediately."
fi

# Rule 6: Sensitive /etc/shadow permissions
if [ "$SHADOW_WORLD_READABLE" = true ]; then
  add_finding \
    "PERM-001" "PERMISSIONS" "CRITICAL" 30 \
    "Sensitive /etc/shadow is world-readable" \
    "Permissions: $SHADOW_PERM" \
    "Unprivileged users or malicious processes can read hashed password hashes and crack them offline." \
    "Run 'chmod 0640 /etc/shadow' and ensure ownership is root:shadow."
fi

# Rule 7: Failed System Services
if [ "$FAILED_SERVICES_COUNT" -gt 0 ]; then
  add_finding \
    "SVC-001" "SYSTEMD" "MODERATE" $(( FAILED_SERVICES_COUNT * 5 > 20 ? 20 : FAILED_SERVICES_COUNT * 5 )) \
    "Failed systemd operational units detected" \
    "$FAILED_SERVICES_COUNT failed service(s): $FAILED_SERVICES_LIST" \
    "Failed system services can cause denial of service or indicate application crashes." \
    "Inspect failed service logs using 'journalctl -xeu <service>' and restart or fix the underlying fault."
fi

# Rule 8: Unpatched Security Updates
if [ "$SECURITY_UPDATES" -gt 0 ]; then
  add_finding \
    "PKG-001" "PACKAGE" "MODERATE" 10 \
    "Unpatched security packages available" \
    "$SECURITY_UPDATES pending security updates ($PKG_MANAGER)" \
    "Pending security patches leave the operating system vulnerable to known publicly disclosed CVE exploits." \
    "Execute system update in your scheduled maintenance window ('apt-get update && apt-get upgrade -y' or 'dnf upgrade')."
fi

# Rule 9: High Root Disk Usage
if [ "$DISK_USAGE_PCT" -ge 90 ]; then
  add_finding \
    "DISK-001" "STORAGE" "HIGH" 15 \
    "Root disk usage critically high" \
    "Disk utilization: ${DISK_USAGE_PCT}%" \
    "Disk exhaustion will cause service failures, log truncation, and potential database corruption." \
    "Clean up rotated logs in /var/log, remove dangling docker images, or expand the volume storage."
fi

# Rule 10: Docker Privileged Containers
if [ "$DOCKER_PRIVILEGED_COUNT" -gt 0 ]; then
  add_finding \
    "DOC-001" "DOCKER" "HIGH" 15 \
    "Containers running in privileged mode detected" \
    "$DOCKER_PRIVILEGED_COUNT privileged container(s)" \
    "Privileged containers have full root capabilities on the host kernel and can escape to compromise the host." \
    "Avoid '--privileged'. Grant specific Linux capabilities using '--cap-add' only where strictly needed."
fi

# Bound score between 0 and 100
[ "$BASE_SCORE" -lt 0 ] && BASE_SCORE=0
[ "$BASE_SCORE" -gt 100 ] && BASE_SCORE=100

# Determine Risk Level Category
if [ "$BASE_SCORE" -ge 90 ]; then
  RISK_LEVEL="LOW"
elif [ "$BASE_SCORE" -ge 75 ]; then
  RISK_LEVEL="MODERATE"
elif [ "$BASE_SCORE" -ge 50 ]; then
  RISK_LEVEL="HIGH"
else
  RISK_LEVEL="CRITICAL"
fi

# Build Findings JSON list
FINDINGS_JSON="[]"
if [ ${#FINDINGS[@]} -gt 0 ]; then
  FINDINGS_JSON="[$(IFS=,; echo "${FINDINGS[*]}")]"
fi

# UID 0 users JSON
UID_ZERO_JSON="[\"root\"]"
if [ ${#UID_ZERO_USERS[@]} -gt 0 ]; then
  UID_ITEMS=()
  for u in "${UID_ZERO_USERS[@]}"; do
    UID_ITEMS+=("\"$u\"")
  done
  UID_ZERO_JSON="[$(IFS=,; echo "${UID_ITEMS[*]}")]"
fi

TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

# -----------------------------------------------------------------------------
# 12. ASSEMBLE AUDIT RESULT JSON
# -----------------------------------------------------------------------------
JSON_PAYLOAD=$(cat <<EOF
{
  "collector_version": "$VERSION",
  "timestamp": "$TIMESTAMP",
  "server": {
    "hostname": "$HOSTNAME",
    "os": "$OS_NAME $OS_VERSION",
    "kernel": "$KERNEL",
    "arch": "$ARCH",
    "uptime": "$UPTIME_STR",
    "uptime_seconds": $UPTIME_SECONDS
  },
  "resources": {
    "cpu_cores": $CPU_CORES,
    "cpu_usage": $CPU_USAGE,
    "load_1m": $LOAD_1M,
    "load_5m": $LOAD_5M,
    "load_15m": $LOAD_15M,
    "memory_total_kb": $MEM_TOTAL,
    "memory_available_kb": $MEM_AVAILABLE,
    "memory_usage": $MEM_USAGE_PCT,
    "disk_total_mb": $DISK_TOTAL_MB,
    "disk_used_mb": $DISK_USED_MB,
    "disk_usage": $DISK_USAGE_PCT,
    "temperature_c": $TEMP_C
  },
  "security": {
    "firewall": "$FIREWALL_STATUS",
    "firewall_type": "$FIREWALL_TYPE",
    "ssh_root_login": "$SSH_PERMIT_ROOT",
    "ssh_password_auth": "$SSH_PASSWORD_AUTH",
    "ssh_pubkey_auth": "$SSH_PUBKEY_AUTH",
    "ssh_port": "$SSH_PORT",
    "ssh_config_valid": $SSH_CONFIG_VALID
  },
  "users": {
    "total_count": $TOTAL_USERS,
    "uid_zero_accounts": $UID_ZERO_JSON,
    "sudo_users_count": $SUDO_USERS_COUNT,
    "login_shell_count": $LOGIN_SHELL_COUNT
  },
  "services": {
    "failed": $FAILED_SERVICES_COUNT,
    "failed_list": $FAILED_SERVICES_LIST
  },
  "packages": {
    "manager": "$PKG_MANAGER",
    "updates_available": $UPDATES_AVAILABLE,
    "security_updates": $SECURITY_UPDATES
  },
  "web_server": {
    "nginx_installed": $NGINX_INSTALLED,
    "nginx_version": "$NGINX_VERSION",
    "nginx_config": "$NGINX_CONFIG_OK",
    "apache_installed": $APACHE_INSTALLED,
    "apache_version": "$APACHE_VERSION"
  },
  "docker": {
    "available": $DOCKER_AVAILABLE,
    "version": "$DOCKER_VERSION",
    "running_containers": $DOCKER_RUNNING_CONTAINERS,
    "privileged_containers": $DOCKER_PRIVILEGED_COUNT
  },
  "file_system": {
    "shadow_permissions": "$SHADOW_PERM",
    "passwd_permissions": "$PASSWD_PERM",
    "shadow_world_readable": $SHADOW_WORLD_READABLE
  },
  "network": {
    "listening_ports": $LISTENING_PORTS_JSON
  },
  "findings": $FINDINGS_JSON,
  "score": $BASE_SCORE,
  "risk_level": "$RISK_LEVEL"
}
EOF
)

# Output handling
if [ "$DRY_RUN" -eq 1 ] || [ -z "$OUTPUT_FILE" ] && [ -z "$SEND_TO_URL" ]; then
  echo "$JSON_PAYLOAD"
fi

if [ -n "$OUTPUT_FILE" ]; then
  echo "$JSON_PAYLOAD" > "$OUTPUT_FILE"
  echo "Audit saved to: $OUTPUT_FILE" >&2
fi

if [ -n "$SEND_TO_URL" ]; then
  echo "Sending audit payload to $SEND_TO_URL..." >&2
  HTTP_HEADER="Content-Type: application/json"
  AUTH_HEADER=""
  if [ -n "$API_TOKEN" ]; then
    AUTH_HEADER="X-Sentinel-Token: $API_TOKEN"
  fi

  HTTP_CODE=$(safe_exec curl -s -o /dev/null -w "%{http_code}" -X POST \
    -H "$HTTP_HEADER" \
    ${AUTH_HEADER:+-H "$AUTH_HEADER"} \
    -d "$JSON_PAYLOAD" \
    "$SEND_TO_URL" || echo "000")

  if [[ "$HTTP_CODE" =~ ^2[0-9]{2}$ ]]; then
    echo "Audit successfully submitted! HTTP $HTTP_CODE" >&2
  else
    echo "Failed to submit audit. HTTP response code: $HTTP_CODE" >&2
    exit 1
  fi
fi

exit 0
