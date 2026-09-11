<?php

namespace Database\Seeders;

use App\Models\AiReport;
use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\Notification;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Default Administrator
        $admin = User::firstOrCreate(
            ['email' => 'admin@sentinel.local'],
            [
                'name'     => 'Security Operations Admin',
                'password' => Hash::make('SentinelAdmin2026!'),
            ]
        );

        // 2. Create Initial Target Server
        $server = Server::firstOrCreate(
            ['hostname' => 'srv-prod-linux01'],
            [
                'name'            => 'Core Production Node',
                'ip_address'      => '192.168.1.100',
                'os_info'         => 'Ubuntu 24.04 LTS (Linux 6.8.0-generic)',
                'description'     => 'Primary production workload server hosting containerized services.',
                'api_token'       => 'stk_demo_master_token_2026_sentinel',
                'status'          => 'active',
                'last_audited_at' => now(),
            ]
        );

        // 3. Create Seed Historical Audits (To generate beautiful historical charts immediately)
        $auditDates = [
            now()->subHours(24) => [
                'score' => 86, 'risk' => 'MODERATE', 'cpu' => 34.2, 'mem' => 52.0, 'disk' => 45.1,
            ],
            now()->subHours(18) => [
                'score' => 86, 'risk' => 'MODERATE', 'cpu' => 38.5, 'mem' => 54.2, 'disk' => 45.3,
            ],
            now()->subHours(12) => [
                'score' => 76, 'risk' => 'MODERATE', 'cpu' => 42.0, 'mem' => 58.6, 'disk' => 46.0,
            ],
            now()->subHours(6) => [
                'score' => 86, 'risk' => 'MODERATE', 'cpu' => 31.0, 'mem' => 48.0, 'disk' => 42.0,
            ],
            now() => [
                'score' => 86, 'risk' => 'MODERATE', 'cpu' => 28.5, 'mem' => 47.2, 'disk' => 42.5,
            ],
        ];

        $latestAudit = null;
        foreach ($auditDates as $date => $meta) {
            $latestAudit = Audit::create([
                'server_id'             => $server->id,
                'score'                 => $meta['score'],
                'risk_level'            => $meta['risk'],
                'cpu_usage'             => $meta['cpu'],
                'memory_usage'          => $meta['mem'],
                'disk_usage'            => $meta['disk'],
                'failed_services_count' => 0,
                'firewall_status'       => 'active',
                'ssh_port'              => '22',
                'raw_payload'           => [
                    'server' => [
                        'hostname' => $server->hostname,
                        'os'       => $server->os_info,
                        'kernel'   => '6.8.0-31-generic',
                        'uptime'   => '10 days, 4 hours',
                    ],
                    'resources' => [
                        'cpu_usage'    => $meta['cpu'],
                        'memory_usage' => $meta['mem'],
                        'disk_usage'   => $meta['disk'],
                    ],
                    'security' => [
                        'firewall'          => 'active',
                        'ssh_root_login'    => 'prohibit-password',
                        'ssh_password_auth' => 'yes',
                        'ssh_pubkey_auth'   => 'yes',
                        'ssh_port'          => '22',
                    ],
                    'services' => ['failed' => 0],
                    'network' => [
                        'listening_ports' => [
                            ['proto' => 'tcp', 'ip' => '0.0.0.0', 'port' => '22'],
                            ['proto' => 'tcp', 'ip' => '0.0.0.0', 'port' => '80'],
                            ['proto' => 'tcp', 'ip' => '0.0.0.0', 'port' => '443'],
                            ['proto' => 'tcp', 'ip' => '127.0.0.1', 'port' => '5432'],
                        ]
                    ],
                    'score' => $meta['score'],
                    'risk_level' => $meta['risk'],
                ],
                'status'     => 'completed',
                'audited_at' => $date,
            ]);
        }

        // 4. Attach Findings to the Latest Audit
        if ($latestAudit) {
            $findings = [
                [
                    'finding_code'   => 'SSH-002',
                    'category'       => 'SSH',
                    'severity'       => 'HIGH',
                    'score_impact'   => 10,
                    'title'          => 'SSH password authentication enabled',
                    'evidence'       => 'PasswordAuthentication yes (in /etc/ssh/sshd_config)',
                    'description'    => 'Password authentication leaves SSH exposed to automated credential attacks and brute-force guessing attempts.',
                    'recommendation' => 'Distribute SSH public keys to all authorized operators, verify connectivity, and configure PasswordAuthentication no in /etc/ssh/sshd_config.',
                ],
                [
                    'finding_code'   => 'SSH-003',
                    'category'       => 'SSH',
                    'severity'       => 'LOW',
                    'score_impact'   => 4,
                    'title'          => 'SSH service listening on default port 22',
                    'evidence'       => 'Port 22',
                    'description'    => 'Default listening port allows internet-wide automated scanners to easily detect and catalog the SSH service.',
                    'recommendation' => 'Migrate SSH listener to a non-standard high port or configure aggressive fail2ban and rate-limiting rules.',
                ],
            ];

            foreach ($findings as $f) {
                AuditFinding::create([
                    'audit_id'       => $latestAudit->id,
                    'finding_code'   => $f['finding_code'],
                    'category'       => $f['category'],
                    'severity'       => $f['severity'],
                    'score_impact'   => $f['score_impact'],
                    'title'          => $f['title'],
                    'evidence'       => $f['evidence'],
                    'description'    => $f['description'],
                    'recommendation' => $f['recommendation'],
                ]);
            }

            // 5. Attach AI Analysis Report
            AiReport::create([
                'audit_id'           => $latestAudit->id,
                'provider'           => '9router (DeepSeek)',
                'model'              => 'deepseek-chat',
                'summary'            => "The production node srv-prod-linux01 demonstrates solid core hygiene with an active firewall and zero failed operational units. The primary exposure stems from password-based SSH authentication.",
                'overall_assessment' => "Moderate Risk (Score: 86/100). The server is resilient against peripheral scanning, but identity hardening on port 22 is required to attain a Low Risk compliance score.",
                'findings_analysis'  => [
                    [
                        'id'             => 'SSH-002',
                        'explanation'    => 'PasswordAuthentication yes allows attackers to mount dictionary attacks against registered accounts without possession of an authorized cryptographic private key.',
                        'impact'         => 'High probability of credential stuffing or unauthorized account takeover if a user maintains a weak password.',
                        'recommendation' => 'Enforce Ed25519 or RSA-4096 public key authentication, configure PasswordAuthentication no, and reload the ssh service.',
                        'priority'       => 'HIGH',
                    ],
                    [
                        'id'             => 'SSH-003',
                        'explanation'    => 'Standard port 22 receives non-stop background noise from automated botnets.',
                        'impact'         => 'Log clutter and minor CPU overhead handling invalid authentication handshakes.',
                        'recommendation' => 'Change port or install fail2ban to immediately ban IPs with repeat failures.',
                        'priority'       => 'LOW',
                    ],
                ],
                'raw_response'       => ['simulated' => true],
            ]);

            // 6. Attach Initial Notification Record
            Notification::create([
                'audit_id' => $latestAudit->id,
                'channel'  => 'system',
                'title'    => 'Initial Baseline Audit Completed',
                'message'  => "Initial security audit for {$server->hostname} completed. Score: 86/100 (MODERATE).",
                'status'   => 'sent',
                'sent_at'  => now(),
            ]);
        }
    }
}
