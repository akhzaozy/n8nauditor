#!/usr/bin/env python3
"""
SentinelAI - Read-Only Linux Server Security & Telemetry Collector (Python 3)
Zero external dependencies. Uses Python standard library only.
Strictly non-intrusive and read-only.
"""

import sys
import os
import subprocess
import socket
import platform
import json
import argparse
import datetime
import urllib.request
import urllib.error

VERSION = "1.0.0"

def safe_run(cmd, timeout=3):
    """Run command safely with timeout, returning stdout string or empty string."""
    try:
        res = subprocess.run(
            cmd,
            shell=True,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            timeout=timeout
        )
        return res.stdout.strip()
    except Exception:
        return ""

def collect_telemetry():
    # Hostname & OS
    hostname = socket.gethostname()
    kernel = platform.release()
    arch = platform.machine()
    
    os_name = platform.system()
    os_version = platform.version()
    if os.path.exists("/etc/os-release"):
        try:
            with open("/etc/os-release") as f:
                for line in f:
                    if line.startswith("PRETTY_NAME="):
                        os_name = line.split("=", 1)[1].strip().strip('"')
                        break
        except Exception:
            pass

    # Uptime
    uptime_str = safe_run("uptime -p") or safe_run("uptime") or "unknown"
    uptime_sec = 0
    if os.path.exists("/proc/uptime"):
        try:
            with open("/proc/uptime") as f:
                uptime_sec = int(float(f.read().split()[0]))
        except Exception:
            pass

    # CPU load
    load_1m, load_5m, load_15m = 0.0, 0.0, 0.0
    try:
        load = os.getloadavg()
        load_1m, load_5m, load_15m = round(load[0], 2), round(load[1], 2), round(load[2], 2)
    except Exception:
        pass

    cpu_cores = os.cpu_count() or 1
    cpu_usage = 0
    top_out = safe_run("top -bn1 | grep 'Cpu(s)'")
    if top_out:
        try:
            parts = top_out.split(",")
            for p in parts:
                if "id" in p:
                    idle = float(p.split()[0].replace("%", ""))
                    cpu_usage = int(100 - idle)
        except Exception:
            pass

    # Memory
    mem_total_kb, mem_avail_kb, mem_usage_pct = 0, 0, 0
    if os.path.exists("/proc/meminfo"):
        try:
            with open("/proc/meminfo") as f:
                for line in f:
                    if line.startswith("MemTotal:"):
                        mem_total_kb = int(line.split()[1])
                    elif line.startswith("MemAvailable:"):
                        mem_avail_kb = int(line.split()[1])
            if mem_total_kb > 0 and mem_avail_kb > 0:
                mem_usage_pct = int(((mem_total_kb - mem_avail_kb) / mem_total_kb) * 100)
        except Exception:
            pass

    # Disk
    disk_total_mb, disk_used_mb, disk_usage_pct = 0, 0, 0
    df_out = safe_run("df -m / | tail -1")
    if df_out:
        parts = df_out.split()
        if len(parts) >= 5:
            try:
                disk_total_mb = int(parts[1])
                disk_used_mb = int(parts[2])
                disk_usage_pct = int(parts[4].replace("%", ""))
            except Exception:
                pass

    # Temperature
    temp_c = None
    if os.path.exists("/sys/class/thermal/thermal_zone0/temp"):
        try:
            with open("/sys/class/thermal/thermal_zone0/temp") as f:
                temp_c = int(int(f.read().strip()) / 1000)
        except Exception:
            pass

    # SSH Audit
    ssh_root = "unknown"
    ssh_pass = "unknown"
    ssh_pubkey = "unknown"
    ssh_port = "22"
    ssh_valid = True

    sshd_test = safe_run("sshd -T")
    if sshd_test:
        for line in sshd_test.splitlines():
            line_l = line.strip().lower()
            if line_l.startswith("permitrootlogin"):
                ssh_root = line_l.split()[-1]
            elif line_l.startswith("passwordauthentication"):
                ssh_pass = line_l.split()[-1]
            elif line_l.startswith("pubkeyauthentication"):
                ssh_pubkey = line_l.split()[-1]
            elif line_l.startswith("port "):
                ssh_port = line_l.split()[-1]
    elif os.path.exists("/etc/ssh/sshd_config"):
        try:
            with open("/etc/ssh/sshd_config") as f:
                for line in f:
                    line_s = line.strip()
                    if line_s.startswith("#") or not line_s:
                        continue
                    parts = line_s.split()
                    if parts[0].lower() == "permitrootlogin":
                        ssh_root = parts[1].lower()
                    elif parts[0].lower() == "passwordauthentication":
                        ssh_pass = parts[1].lower()
                    elif parts[0].lower() == "pubkeyauthentication":
                        ssh_pubkey = parts[1].lower()
                    elif parts[0].lower() == "port":
                        ssh_port = parts[1]
        except Exception:
            pass

    test_syntax = safe_run("sshd -t")
    # If exit code was non-zero, safe_run might return empty, but let's check
    res_code = subprocess.run("sshd -t 2>/dev/null", shell=True).returncode
    if res_code != 0:
        ssh_valid = False

    # Firewall
    firewall_type = "none"
    firewall_status = "inactive"
    if safe_run("which ufw"):
        firewall_type = "ufw"
        if "Status: active" in safe_run("ufw status"):
            firewall_status = "active"
    elif safe_run("which firewall-cmd"):
        firewall_type = "firewalld"
        if "running" in safe_run("firewall-cmd --state"):
            firewall_status = "active"
    elif safe_run("which nft"):
        firewall_type = "nftables"
        if safe_run("nft list ruleset"):
            firewall_status = "active"
    elif safe_run("which iptables"):
        firewall_type = "iptables"
        if safe_run("iptables -L -n"):
            firewall_status = "active"

    # Ports
    listening_ports = []
    ss_out = safe_run("ss -tuln")
    if ss_out:
        for line in ss_out.splitlines()[1:]:
            parts = line.split()
            if len(parts) >= 5:
                proto = parts[0]
                addr = parts[4]
                if ":" in addr:
                    ip, port = addr.rsplit(":", 1)
                    listening_ports.append({"proto": proto, "ip": ip, "port": port})

    # Users
    total_users = 0
    uid_zero = []
    login_shells = 0
    if os.path.exists("/etc/passwd"):
        try:
            with open("/etc/passwd") as f:
                for line in f:
                    total_users += 1
                    fields = line.strip().split(":")
                    if len(fields) >= 7:
                        if fields[2] == "0":
                            uid_zero.append(fields[0])
                        if fields[6] in ("/bin/bash", "/bin/sh", "/bin/zsh", "/bin/dash"):
                            login_shells += 1
        except Exception:
            pass

    sudo_users = 0
    getent_out = safe_run("getent group sudo wheel")
    if getent_out:
        s_users = set()
        for line in getent_out.splitlines():
            f = line.strip().split(":")
            if len(f) >= 4 and f[3]:
                for u in f[3].split(","):
                    if u.strip():
                        s_users.add(u.strip())
        sudo_users = len(s_users)

    # Systemd failed services
    failed_services = []
    svc_out = safe_run("systemctl list-units --state=failed --no-legend --plain")
    if svc_out:
        for line in svc_out.splitlines():
            svc = line.split()[0].strip()
            if svc:
                failed_services.append(svc)

    # Packages
    pkg_mgr = "unknown"
    updates = 0
    sec_updates = 0
    if safe_run("which apt-get"):
        pkg_mgr = "apt"
        sim = safe_run("apt-get -s upgrade")
        if sim:
            for line in sim.splitlines():
                if "upgraded," in line:
                    updates = int(line.split()[0])
                if "security" in line.lower() or "esm" in line.lower():
                    sec_updates += 1
    elif safe_run("which dnf"):
        pkg_mgr = "dnf"
        dnf_sec = safe_run("dnf check-update --security -q")
        if dnf_sec:
            sec_updates = len([l for l in dnf_sec.splitlines() if l.strip()])

    # Web & Docker
    nginx_installed = bool(safe_run("which nginx"))
    nginx_ver = safe_run("nginx -v 2>&1").replace("nginx version: ", "") if nginx_installed else "not-installed"
    nginx_cfg = "valid" if nginx_installed and subprocess.run("nginx -t -q 2>/dev/null", shell=True).returncode == 0 else "unknown"

    apache_installed = bool(safe_run("which apache2") or safe_run("which httpd"))
    docker_avail = bool(safe_run("which docker") and subprocess.run("docker info >/dev/null 2>&1", shell=True).returncode == 0)
    docker_ver = safe_run("docker --version").replace("Docker version ", "").split(",")[0] if docker_avail else "not-installed"
    docker_containers = len(safe_run("docker ps -q").split()) if docker_avail else 0

    # Permissions
    shadow_perm = safe_run("stat -c '%a' /etc/shadow") or safe_run("stat -f '%Op' /etc/shadow") or "unknown"
    passwd_perm = safe_run("stat -c '%a' /etc/passwd") or safe_run("stat -f '%Op' /etc/passwd") or "unknown"
    shadow_world_readable = False
    if shadow_perm != "unknown" and len(shadow_perm) >= 1:
        if shadow_perm[-1] in ("4", "5", "6", "7"):
            shadow_world_readable = True

    # Rule Findings & Scoring Engine
    score = 100
    findings = []

    def add_finding(fid, cat, sev, impact, title, evidence, desc, rec):
        nonlocal score
        score -= impact
        findings.append({
            "id": fid,
            "category": cat,
            "severity": sev,
            "score_impact": impact,
            "title": title,
            "evidence": evidence,
            "description": desc,
            "recommendation": rec
        })

    if ssh_root == "yes":
        add_finding("SSH-001", "SSH", "HIGH", 15,
                    "Direct SSH root login allowed",
                    "PermitRootLogin yes",
                    "Permitting direct root login allows remote attackers to target root directly.",
                    "Set 'PermitRootLogin prohibit-password' or 'no' in /etc/ssh/sshd_config.")

    if ssh_pass == "yes":
        add_finding("SSH-002", "SSH", "HIGH", 10,
                    "SSH password authentication enabled",
                    "PasswordAuthentication yes",
                    "Password authentication is susceptible to brute-force credential stuffing.",
                    "Enforce SSH key pair authentication and set 'PasswordAuthentication no'.")

    if ssh_port == "22":
        add_finding("SSH-003", "SSH", "LOW", 5,
                    "Default SSH port 22 in use",
                    "Port 22",
                    "Running on default port 22 exposes host to common automated botnet probes.",
                    "Consider using a non-standard port or firewall/fail2ban rate limiting.")

    if firewall_status != "active":
        add_finding("FW-001", "FIREWALL", "CRITICAL", 25,
                    "Host firewall is inactive or disabled",
                    f"Firewall status: {firewall_status} ({firewall_type})",
                    "Without an active firewall, listening ports are directly accessible.",
                    "Enable and configure ufw, firewalld, or nftables.")

    if len(uid_zero) > 1:
        add_finding("USR-001", "USER", "CRITICAL", 30,
                    "Multiple UID 0 superuser accounts detected",
                    f"UID 0 accounts: {', '.join(uid_zero)}",
                    "Accounts other than root with UID 0 indicate severe misconfiguration or backdoor.",
                    "Investigate and remove non-root UID 0 accounts from /etc/passwd.")

    if shadow_world_readable:
        add_finding("PERM-001", "PERMISSIONS", "CRITICAL", 30,
                    "Sensitive /etc/shadow is world-readable",
                    f"Permissions: {shadow_perm}",
                    "Password hashes can be read and cracked offline by unprivileged processes.",
                    "Run 'chmod 0640 /etc/shadow' and verify ownership.")

    if len(failed_services) > 0:
        impact = min(20, len(failed_services) * 5)
        add_finding("SVC-001", "SYSTEMD", "MODERATE", impact,
                    "Failed systemd operational units detected",
                    f"{len(failed_services)} failed service(s): {', '.join(failed_services)}",
                    "Failed system units indicate service crashes or disrupted system operations.",
                    "Inspect with 'journalctl -xeu <service>' and restart or resolve configuration.")

    if sec_updates > 0:
        add_finding("PKG-001", "PACKAGE", "MODERATE", 10,
                    "Unpatched security packages available",
                    f"{sec_updates} pending security updates ({pkg_mgr})",
                    "Pending security patches leave the server exposed to publicly known exploits.",
                    "Schedule system update during maintenance window.")

    if disk_usage_pct >= 90:
        add_finding("DISK-001", "STORAGE", "HIGH", 15,
                    "Root disk usage critically high",
                    f"Disk utilization: {disk_usage_pct}%",
                    "Disk space exhaustion can crash critical services and corrupt databases.",
                    "Free space in /var/log or docker prune dangling images.")

    score = max(0, min(100, score))
    risk_level = "LOW" if score >= 90 else "MODERATE" if score >= 75 else "HIGH" if score >= 50 else "CRITICAL"

    data = {
        "collector_version": VERSION,
        "timestamp": datetime.datetime.now(datetime.timezone.utc).isoformat().replace("+00:00", "Z"),
        "server": {
            "hostname": hostname,
            "os": os_name,
            "kernel": kernel,
            "arch": arch,
            "uptime": uptime_str,
            "uptime_seconds": uptime_sec
        },
        "resources": {
            "cpu_cores": cpu_cores,
            "cpu_usage": cpu_usage,
            "load_1m": load_1m,
            "load_5m": load_5m,
            "load_15m": load_15m,
            "memory_total_kb": mem_total_kb,
            "memory_available_kb": mem_avail_kb,
            "memory_usage": mem_usage_pct,
            "disk_total_mb": disk_total_mb,
            "disk_used_mb": disk_used_mb,
            "disk_usage": disk_usage_pct,
            "temperature_c": temp_c
        },
        "security": {
            "firewall": firewall_status,
            "firewall_type": firewall_type,
            "ssh_root_login": ssh_root,
            "ssh_password_auth": ssh_pass,
            "ssh_pubkey_auth": ssh_pubkey,
            "ssh_port": ssh_port,
            "ssh_config_valid": ssh_valid
        },
        "users": {
            "total_count": total_users,
            "uid_zero_accounts": uid_zero or ["root"],
            "sudo_users_count": sudo_users,
            "login_shell_count": login_shells
        },
        "services": {
            "failed": len(failed_services),
            "failed_list": failed_services
        },
        "packages": {
            "manager": pkg_mgr,
            "updates_available": updates,
            "security_updates": sec_updates
        },
        "web_server": {
            "nginx_installed": nginx_installed,
            "nginx_version": nginx_ver,
            "nginx_config": nginx_cfg,
            "apache_installed": apache_installed
        },
        "docker": {
            "available": docker_avail,
            "version": docker_ver,
            "running_containers": docker_containers,
            "privileged_containers": 0
        },
        "file_system": {
            "shadow_permissions": shadow_perm,
            "passwd_permissions": passwd_perm,
            "shadow_world_readable": shadow_world_readable
        },
        "network": {
            "listening_ports": listening_ports
        },
        "findings": findings,
        "score": score,
        "risk_level": risk_level
    }
    return data

def main():
    parser = argparse.ArgumentParser(description="SentinelAI Collector v" + VERSION)
    parser.add_argument("--dry-run", action="store_true", help="Print JSON to stdout")
    parser.add_argument("-o", "--output", help="Save JSON to file")
    parser.add_argument("-s", "--send-to", help="POST JSON to webhook / SentinelAI ingest API")
    parser.add_argument("-t", "--api-token", help="Bearer / X-Sentinel-Token")
    args = parser.parse_args()

    data = collect_telemetry()
    json_str = json.dumps(data, indent=2)

    if args.dry_run or (not args.output and not args.send_to):
        print(json_str)

    if args.output:
        with open(args.output, "w") as f:
            f.write(json_str)
        sys.stderr.write(f"Audit output saved to {args.output}\n")

    if args.send_to:
        sys.stderr.write(f"Sending audit to {args.send_to}...\n")
        req = urllib.request.Request(
            args.send_to,
            data=json_str.encode("utf-8"),
            headers={"Content-Type": "application/json"}
        )
        if args.api_token:
            req.add_header("X-Sentinel-Token", args.api_token)
        try:
            with urllib.request.urlopen(req, timeout=10) as resp:
                sys.stderr.write(f"Audit submitted successfully! HTTP {resp.status}\n")
        except urllib.error.HTTPError as e:
            sys.stderr.write(f"HTTP Error {e.code}: {e.read().decode('utf-8')}\n")
            sys.exit(1)
        except Exception as e:
            sys.stderr.write(f"Connection failed: {e}\n")
            sys.exit(1)

if __name__ == "__main__":
    main()
