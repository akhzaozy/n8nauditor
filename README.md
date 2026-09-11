# SentinelAI - AI-Powered Server Security & Risk Auditor

[![Docker Compose](https://img.shields.io/badge/Docker_Compose-Supported-blue?logo=docker)](file:///docker-compose.yml)
[![Portainer Stack](https://img.shields.io/badge/Portainer-Stack_Ready-0099e5?logo=portainer)](file:///docker-compose.yml)
[![Laravel 12](https://img.shields.io/badge/Backend-Laravel_12_PHP_8.3-red?logo=laravel)](file:///app)
[![n8n Pipeline](https://img.shields.io/badge/Automation-n8n_v1.80-FF6D5A?logo=n8n)](file:///n8n)
[![Security](https://img.shields.io/badge/Architecture-Read--Only_Zero_Shell_Access-emerald)](file:///audit-agent)

**SentinelAI** adalah platform audit keamanan dan pemantauan risiko server Linux yang dirancang khusus untuk server operasional berkinerja tinggi. SentinelAI menginspeksi konfigurasi server secara **100% read-only**, menghitung skor risiko terukur (0–100), menjalankan otomasi audit terjadwal melalui **n8n**, dan memperkaya analisis risiko menggunakan AI modular (**9router**, **OpenAI**, maupun **Ollama/DeepSeek lokal**) tanpa pernah memberikan AI akses shell langsung atau kemampuan menjalankan perintah destruktif.

---

## 1. Requirements

Sebelum memulai, pastikan server Anda telah memenuhi spesifikasi minimal berikut:

- **Sistem Operasi**: Linux (Ubuntu 22.04/24.04, Debian 11/12, Rocky Linux 9, CentOS Stream, Alpine) atau macOS/WSL2 untuk development.
- **Container Runtime**:
  - Docker Engine $\ge$ 24.0
  - Docker Compose v2 (`docker compose`)
  - Atau Portainer Server $\ge$ 2.19
- **Resource Minimal**:
  - CPU: 2 Cores
  - RAM: 2 GB (tersedia limit aman pada compose file agar tidak membebani service utama)
  - Disk: 10 GB ruang kosong
- **Network Ports**:
  - `8080` (Web Dashboard SentinelAI melalui Nginx reverse proxy)
  - `5678` (n8n Web UI & Webhooks)

---

## 2. Arsitektur SentinelAI

```
Linux Server (Host)
       ↓
Sentinel Agent / Audit Collector (Read-Only)
       ↓ (Sanitized JSON Telemetry)
      n8n (Automation Workflow)
       ↓
Rule-based Risk Engine (Deterministic Scoring 0-100)
       ↓ (Findings & Evidence)
Modular AI Analysis (9router / OpenAI / Ollama)
       ↓
PostgreSQL Database
       ↓
Web Dashboard (Laravel 12 + Blade + Tailwind + Alpine.js + Chart.js)
       ↓
Notification Dispatcher (Discord / Telegram / Webhook)
```

---

## 3. Struktur Project

```
sentinel-ai/
├── docker-compose.yml               # Production & Portainer ready stack
├── .env.example                     # Template konfigurasi environment
├── README.md                        # Dokumentasi deployment & operasional
├── nginx/
│   └── default.conf                 # Konfigurasi Nginx reverse proxy
├── app/                             # SentinelAI Web Application (Laravel 12 / PHP 8.3-FPM)
│   ├── Dockerfile
│   ├── composer.json
│   ├── app/
│   │   ├── Http/Controllers/        # Dashboard, Audit, Server, Finding, Setting, Ingest API
│   │   ├── Models/                 # User, Server, Audit, AuditFinding, AiReport, Notification
│   │   └── Services/               # RiskScoringEngine, AiAnalysisService, NotificationService
│   ├── database/
│   │   ├── migrations/             # PostgreSQL Schema Migrations
│   │   └── seeders/DatabaseSeeder.php
│   └── resources/views/            # Modern Dark Theme Blade Templates
├── audit-agent/
│   ├── sentinel-collector.sh        # Zero-dependency POSIX/Bash Read-Only Collector
│   ├── sentinel-collector.py        # Python 3 Read-Only Collector
│   └── README.md
├── n8n/
│   ├── workflows/
│   │   └── sentinel_audit_pipeline.json # Pre-built n8n audit pipeline workflow
│   └── README.md
└── scripts/
    ├── init.sh                      # Helper bootstrap folder & permission
    └── run-audit.sh                 # Helper trigger audit manual ke API/n8n
```

---

## 4. Portainer Deployment (Step-by-Step)

SentinelAI dirancang agar dapat dideploy langsung dari antarmuka Web Editor Portainer:

1. Buka dashboard **Portainer** Anda.
2. Masuk ke environment Docker target, klik menu **Stacks** &rarr; **Add Stack**.
3. Beri nama stack, contoh: `sentinel-ai`.
4. Pada tab **Build method**, pilih **Web editor**.
5. Salin seluruh isi file [`docker-compose.yml`](file:///docker-compose.yml) dan tempel ke dalam editor Portainer.
6. Pada bagian **Environment variables**, klik **Advanced mode** lalu salin seluruh isi file [`.env.example`](file:///..env.example) dan sesuaikan:
   - `APP_KEY`: Generate base64 key atau gunakan default saat inisialisasi.
   - `DB_PASSWORD`: Password database PostgreSQL yang kuat.
   - `AI_PROVIDER`: Pilih `9router`, `openai`, atau `ollama`.
   - `AI_BASE_URL` & `AI_API_KEY`: Kredensial AI Anda.
   - `SENTINEL_API_SECRET`: Secret token unik untuk collector & webhook.
7. Klik **Deploy the stack**.
8. Tunggu hingga semua 5 container (`sentinel-nginx`, `sentinel-app`, `sentinel-worker`, `sentinel-db`, `sentinel-n8n`) berstatus *Healthy/Running*.

---

## 5. Deployment via CLI (Docker Compose)

Jika Anda mendeploy langsung melalui terminal Linux:

```bash
# 1. Masuk ke direktori project
cd /opt/sentinel-ai

# 2. Jalankan script inisialisasi permission
chmod +x scripts/*.sh audit-agent/*.sh audit-agent/*.py
./scripts/init.sh

# 3. Buat file .env dari template
cp .env.example .env
nano .env # Sesuaikan AI_API_KEY, DB_PASSWORD, dan SENTINEL_API_SECRET

# 4. Jalankan stack container
docker compose up -d --build

# 5. Cek status container
docker compose ps
```

---

## 6. Database Migration & Initial Seeder

Setelah container berjalan pertama kali, jalankan migrasi database dan buat akun administrator awal:

```bash
docker compose exec app php artisan migrate:fresh --seed --force
```

Perintah di atas akan:
1. Membangun seluruh tabel database PostgreSQL (`users`, `servers`, `audits`, `audit_findings`, `ai_reports`, `notifications`).
2. Membuat akun administrator awal:
   - **Email**: `admin@sentinel.local`
   - **Password**: `SentinelAdmin2026!`
3. Mendaftarkan server contoh (*Core Production Node*) dengan data audit historis sehingga grafik dan widget dashboard langsung aktif dan menarik saat login pertama kali.

---

## 7. First Login & Dashboard

1. Buka browser dan akses:
   ```
   http://IP_SERVER_ANDA:8080
   ```
2. Halaman login SentinelAI akan muncul dengan dark mode.
3. Masukkan kredensial administrator:
   - **Email**: `admin@sentinel.local`
   - **Password**: `SentinelAdmin2026!`
4. Anda akan diarahkan ke dashboard utama dengan tampilan:
   - **Security Score**: 0–100 dengan indikator warna (Hijau, Kuning, Oranye, Merah).
   - **Risk Badge**: `LOW`, `MODERATE`, `HIGH`, `CRITICAL`.
   - **AI Risk Assessment**: Ringkasan eksekutif dan rekomendasi strategis.
   - **8 Quick Metric Cards**: CPU, RAM, Disk, Uptime, Firewall, SSH, Failed Units, Listening Ports.
   - **Chart.js Trends**: Tren Skor Keamanan, Utilisasi Resource, dan Distribusi Severity Temuan.
   - **Interactive Findings Drawer**: Klik temuan untuk melihat evidence, dampak risiko, dan rekomendasi perbaikan.

---

## 8. Menjalankan Audit Pertama

SentinelAI menyediakan script audit collector yang **100% read-only**:

### Opsi A: Dry-Run di Terminal (Lihat JSON Telemetry)
```bash
./audit-agent/sentinel-collector.sh --dry-run
# atau menggunakan Python 3
python3 ./audit-agent/sentinel-collector.py --dry-run
```

### Opsi B: Kirim Langsung ke SentinelAI API
```bash
./audit-agent/sentinel-collector.sh \
  --send-to "http://localhost:8080/api/v1/audits/ingest" \
  --api-token "sentinel-secret-token-change-me"
```

### Opsi C: Otomatisasi Terjadwal via Linux Crontab (Setiap 6 Jam)
Tambahkan ke `/etc/crontab` server target:
```cron
0 */6 * * * root /opt/sentinel-ai/audit-agent/sentinel-collector.sh --send-to "http://localhost:8080/api/v1/audits/ingest" --api-token "sentinel-secret-token-change-me" >/dev/null 2>&1
```

---

## 9. Menghubungkan n8n Automation Pipeline

n8n berperan sebagai engine otomatisasi yang dapat menjadwalkan audit, memanggil AI gateway, dan mengirimkan notifikasi.

1. Buka n8n di browser: `http://IP_SERVER_ANDA:5678`.
2. Klik **Workflows** &rarr; **Add Workflow**.
3. Klik menu **...** di kanan atas &rarr; pilih **Import from File**.
4. Pilih file: [`n8n/workflows/sentinel_audit_pipeline.json`](file:///n8n/workflows/sentinel_audit_pipeline.json).
5. Workflow ini mencakup:
   - **Schedule Trigger**: Berjalan otomatis setiap 6 jam.
   - **Webhook Trigger (`POST /webhook/sentinel-audit`)**: Menerima audit dari collector atau trigger manual tombol dashboard.
   - **Parse & Validate JSON**: Memeriksa integritas skema data.
   - **AI Risk Analysis**: Meneruskan temuan ke AI Gateway (OpenAI-compatible / 9router / Ollama) dengan strict prompt.
   - **Save Audit to API**: Menyimpan hasil ke SentinelAI endpoint `/api/v1/audits/ingest`.
   - **Filter High/Critical Risks**: Mengirim notifikasi jika skor risiko jatuh ke kategori `HIGH` atau `CRITICAL`.
6. Aktifkan toggle **Active** pada workflow n8n.

---

## 10. Konfigurasi AI Provider (Modular)

SentinelAI mendukung provider AI yang kompatibel dengan format OpenAI chat completions. Ubah konfigurasi melalui `.env` atau menu **AI & Settings** di dashboard.

### Provider 1: 9router (Default)
```env
AI_PROVIDER=9router
AI_BASE_URL=https://api.9router.com/v1
AI_API_KEY=your-9router-api-key
AI_MODEL=deepseek-chat
```

### Provider 2: OpenAI Resmi
```env
AI_PROVIDER=openai
AI_BASE_URL=https://api.openai.com/v1
AI_API_KEY=sk-proj-xxxxxxxxxxxxxxxx
AI_MODEL=gpt-4o-mini
```

### Provider 3: Local Ollama / DeepSeek-R1 (Tanpa Biaya API)
Jika Anda memiliki Ollama yang berjalan pada host atau jaringan lokal:
```env
AI_PROVIDER=ollama
AI_BASE_URL=http://host.docker.internal:11434/v1
AI_API_KEY=ollama
AI_MODEL=deepseek-r1:8b
```

> **Testing Koneksi AI**: Masuk ke menu **AI & Settings** pada dashboard SentinelAI, lalu klik **Test AI Connectivity & Reasoning** untuk memvalidasi respons model secara langsung.

---

## 11. Read-Only Security & Safety Enforcements

Sistem ini didesain dengan prinsip **Least Privilege** dan **Zero Destructive Capability**:

1. **Zero Shell Access**: AI **tidak memiliki terminal, SSH, atau akses eksekusi perintah**. AI hanya menerima ringkasan temuan dalam bentuk data JSON dan memformulasikan penjelasan risiko.
2. **Read-Only Collector**: Perintah di dalam collector menggunakan opsi non-destruktif (`sshd -T`, `ufw status`, `ss -tuln`, `systemctl list-units --state=failed`, `stat -c %a`).
3. **Data Scrubbing**: Script **tidak pernah membaca file sensitif** seperti private keys, password, ataupun isi `/etc/shadow`. Hanya status bit permission (seperti `0640`) yang diinspeksi.
4. **Isolated Network**: Database PostgreSQL dan PHP-FPM berkomunikasi melalui bridge internal Docker (`sentinel_network`) tanpa mengekspos port database langsung ke publik.
5. **Resource Guardrails**: Container Compose dilengkapi CPU dan RAM limits (`deploy.resources.limits`) agar tidak mengganggu service produksi utama server.

---

## 12. Healthcheck & Monitoring Endpoint

Endpoint monitoring bawaan:

```bash
curl -i http://localhost:8080/health
```

Output:
```json
{
  "status": "ok",
  "app": "SentinelAI",
  "database": "ok",
  "timestamp": "2026-09-11T07:55:00+00:00"
}
```

---

## 13. Troubleshooting

| Gejala | Penyebab Umum | Solusi |
| :--- | :--- | :--- |
| **Nginx 502 Bad Gateway** | Container `sentinel-app` belum selesai booting atau PHP-FPM mati | Jalankan `docker compose logs app` untuk melihat log PHP-FPM. Pastikan migrasi database berhasil. |
| **Database Connection Refused** | PostgreSQL sedang melakukan initial cluster setup | Tunggu 10-15 detik hingga healthcheck database berstatus *healthy*. |
| **Audit Collector HTTP 401** | Token pada header `X-Sentinel-Token` tidak cocok dengan `SENTINEL_API_SECRET` | Pastikan flag `--api-token` sesuai dengan `SENTINEL_API_SECRET` pada file `.env`. |
| **AI Timeout / Fallback Alert** | API Key tidak valid atau firewall memblokir outbound HTTPS | Cek koneksi ke provider. Jika API offline, SentinelAI otomatis menggunakan **Rule Engine Fallback** tanpa merusak pipeline. |
| **Portainer Stack Error** | Port 8080 atau 5678 telah digunakan oleh aplikasi lain | Ubah `APP_PORT` (misal ke `8085`) dan `N8N_PORT` pada file `.env`. |

---

## 14. Lisensi

SentinelAI dilisensikan di bawah lisensi MIT.
Dikembangkan untuk pengawasan keamanan Linux terotomasi yang aman, efisien, dan ramah beban kerja server operasional.
