@extends('layouts.app', ['title' => 'Server Security Dashboard'])

@section('content')
<div class="space-y-6">

    <!-- Header & Controls -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-2 border-b border-dark-600/40">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-black tracking-tight text-white uppercase font-sans">Server Security</h1>
                @if($selectedServer)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-dark-700 border border-dark-600 text-slate-300">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        {{ $selectedServer->hostname }}
                    </span>
                @endif
            </div>
            <p class="text-xs text-slate-400 mt-1">Real-time Linux posture evaluation, risk scoring & AI executive findings.</p>
        </div>

        <div class="flex items-center gap-3">
            <!-- Server Selector -->
            @if($servers->count() > 1)
                <form method="GET" action="{{ route('dashboard') }}" class="flex items-center">
                    <select name="server_id" onchange="this.form.submit()" 
                            class="bg-dark-800 border border-dark-600 text-slate-200 text-xs rounded-xl px-3 py-2 focus:outline-none focus:border-purple-accent">
                        @foreach($servers as $srv)
                            <option value="{{ $srv->id }}" {{ $selectedServer?->id === $srv->id ? 'selected' : '' }}>
                                {{ $srv->name }} ({{ $srv->hostname }})
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif

            <!-- Manual Audit Trigger Button -->
            <form action="{{ route('audits.trigger') }}" method="POST">
                @csrf
                <input type="hidden" name="server_id" value="{{ $selectedServer?->id }}">
                <button type="submit" 
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-purple to-purple-accent hover:from-purple-accent hover:to-purple text-white text-xs font-semibold shadow-lg shadow-purple/20 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    Run Security Audit
                </button>
            </form>
        </div>
    </div>

    @if(!$latestAudit)
        <!-- Empty State -->
        <div class="bg-dark-800 border border-dark-600 rounded-2xl p-12 text-center">
            <div class="w-16 h-16 rounded-2xl bg-purple/10 border border-purple/20 text-purple-accent flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3 class="text-lg font-bold text-white mb-2">No Audits Recorded Yet</h3>
            <p class="text-sm text-slate-400 max-w-md mx-auto mb-6">Run your first audit collector or trigger the automation pipeline from n8n to generate security scores and AI analysis.</p>
            <form action="{{ route('audits.trigger') }}" method="POST">
                @csrf
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-purple-accent text-white text-sm font-semibold hover:bg-purple transition-all">
                    Start Initial Audit
                </button>
            </form>
        </div>
    @else
        <!-- Hero Section: Score & Risk Gauge -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Security Score Card -->
            <div class="bg-dark-800 border border-dark-600/70 rounded-2xl p-6 relative overflow-hidden shadow-xl flex flex-col justify-between">
                <div class="absolute -right-10 -top-10 w-40 h-40 bg-purple/10 rounded-full blur-3xl pointer-events-none"></div>
                <div>
                    <span class="text-xs uppercase tracking-wider font-semibold text-slate-400">Security & Risk Posture</span>
                    <div class="flex items-baseline gap-4 mt-3">
                        <span class="text-6xl font-black tracking-tight" style="color: {{ $latestAudit->score_hex_color }}">
                            {{ $latestAudit->score }}
                        </span>
                        <span class="text-2xl font-bold text-slate-500">/ 100</span>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-dark-600/50 flex items-center justify-between">
                    <div>
                        <span class="text-[11px] uppercase tracking-wider text-slate-400 block mb-1">Risk Classification</span>
                        <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold border {{ $latestAudit->risk_badge_color }}">
                            {{ $latestAudit->risk_level }}
                        </span>
                    </div>
                    <div class="text-right">
                        <span class="text-[11px] uppercase tracking-wider text-slate-400 block mb-1">Last Evaluation</span>
                        <span class="text-xs font-mono text-slate-300">{{ $latestAudit->audited_at->diffForHumans() }}</span>
                    </div>
                </div>
            </div>

            <!-- AI Executive Assessment Card -->
            <div class="lg:col-span-2 bg-dark-800 border border-dark-600/70 rounded-2xl p-6 shadow-xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-md bg-purple/20 flex items-center justify-center text-purple-accent">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            </div>
                            <span class="text-xs uppercase tracking-wider font-semibold text-slate-300">AI Risk Analysis Summary</span>
                        </div>
                        <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-dark-700 border border-dark-600 text-purple-glow">
                            {{ $latestAudit->aiReport->provider ?? config('sentinel.ai.provider') }} &bull; {{ $latestAudit->aiReport->model ?? 'deepseek-chat' }}
                        </span>
                    </div>

                    <p class="text-sm text-slate-200 leading-relaxed">
                        {{ $latestAudit->aiReport->summary ?? 'Rule-based verification completed. Review the individual evidence findings below to apply remediation.' }}
                    </p>

                    @if(!empty($latestAudit->aiReport->overall_assessment))
                        <div class="mt-3 p-3 rounded-xl bg-dark-900/60 border border-dark-600/60 text-xs text-slate-400">
                            <strong class="text-slate-200">Strategic Impact:</strong> {{ $latestAudit->aiReport->overall_assessment }}
                        </div>
                    @endif
                </div>

                <div class="mt-4 pt-3 border-t border-dark-600/40 flex items-center justify-between text-xs text-slate-400">
                    <span>Identified Issues: <strong class="text-white">{{ $latestAudit->findings->count() }}</strong></span>
                    <a href="{{ route('audits.show', $latestAudit->id) }}" class="text-purple-accent hover:text-purple-glow font-medium flex items-center gap-1">
                        View Full Audit Payload &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- 8 Core System Metric Cards (from Section 10) -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <!-- CPU Card -->
            <div class="bg-dark-800 border border-dark-600/60 rounded-xl p-4 shadow">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block mb-1">CPU Usage</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-xl font-bold font-mono text-white">{{ $latestAudit->cpu_usage ?? 0 }}%</span>
                    <span class="text-xs text-slate-400 font-mono">{{ $latestAudit->raw_payload['resources']['cpu_cores'] ?? 1 }} Cores</span>
                </div>
                <div class="w-full bg-dark-700 h-1.5 rounded-full mt-2 overflow-hidden">
                    <div class="bg-purple-accent h-full rounded-full" style="width: {{ min(100, $latestAudit->cpu_usage ?? 0) }}%"></div>
                </div>
            </div>

            <!-- RAM Card -->
            <div class="bg-dark-800 border border-dark-600/60 rounded-xl p-4 shadow">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block mb-1">RAM Usage</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-xl font-bold font-mono text-white">{{ $latestAudit->memory_usage ?? 0 }}%</span>
                    <span class="text-xs text-slate-400 font-mono">
                        {{ round(($latestAudit->raw_payload['resources']['memory_total_kb'] ?? 0) / 1024 / 1024, 1) }} GB
                    </span>
                </div>
                <div class="w-full bg-dark-700 h-1.5 rounded-full mt-2 overflow-hidden">
                    <div class="bg-purple-accent h-full rounded-full" style="width: {{ min(100, $latestAudit->memory_usage ?? 0) }}%"></div>
                </div>
            </div>

            <!-- Disk Card -->
            <div class="bg-dark-800 border border-dark-600/60 rounded-xl p-4 shadow">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block mb-1">Disk Utilization</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-xl font-bold font-mono text-white">{{ $latestAudit->disk_usage ?? 0 }}%</span>
                    <span class="text-xs text-slate-400 font-mono">
                        {{ round(($latestAudit->raw_payload['resources']['disk_used_mb'] ?? 0) / 1024, 1) }} / {{ round(($latestAudit->raw_payload['resources']['disk_total_mb'] ?? 0) / 1024, 1) }} GB
                    </span>
                </div>
                <div class="w-full bg-dark-700 h-1.5 rounded-full mt-2 overflow-hidden">
                    <div class="bg-{{ ($latestAudit->disk_usage ?? 0) >= 90 ? 'rose-500' : 'purple-accent' }} h-full rounded-full" style="width: {{ min(100, $latestAudit->disk_usage ?? 0) }}%"></div>
                </div>
            </div>

            <!-- Uptime Card -->
            <div class="bg-dark-800 border border-dark-600/60 rounded-xl p-4 shadow">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block mb-1">System Uptime</span>
                <div class="text-sm font-semibold text-slate-200 truncate mt-1" title="{{ $latestAudit->raw_payload['server']['uptime'] ?? 'Active' }}">
                    {{ $latestAudit->raw_payload['server']['uptime'] ?? 'Active' }}
                </div>
                <span class="text-[11px] text-slate-500 font-mono block mt-2">Kernel: {{ $latestAudit->raw_payload['server']['kernel'] ?? 'Linux' }}</span>
            </div>

            <!-- Firewall Card -->
            <div class="bg-dark-800 border border-dark-600/60 rounded-xl p-4 shadow">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block mb-1">Firewall</span>
                <div class="flex items-center gap-2 mt-1">
                    @if($latestAudit->firewall_status === 'active')
                        <span class="inline-flex items-center gap-1 text-emerald-400 font-semibold text-sm">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Active
                        </span>
                        <span class="text-xs text-slate-500 font-mono">({{ $latestAudit->raw_payload['security']['firewall_type'] ?? 'ufw' }})</span>
                    @else
                        <span class="inline-flex items-center gap-1 text-rose-400 font-semibold text-sm">
                            <span class="w-2 h-2 rounded-full bg-rose-400 animate-ping"></span> Inactive
                        </span>
                    @endif
                </div>
            </div>

            <!-- SSH Security Card -->
            <div class="bg-dark-800 border border-dark-600/60 rounded-xl p-4 shadow">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block mb-1">SSH Hardening</span>
                <div class="text-xs font-mono space-y-1 mt-1 text-slate-300">
                    <div>Port: <span class="text-white">{{ $latestAudit->ssh_port ?? '22' }}</span></div>
                    <div>Root: <span class="{{ ($latestAudit->raw_payload['security']['ssh_root_login'] ?? '') === 'yes' ? 'text-rose-400 font-bold' : 'text-emerald-400' }}">{{ $latestAudit->raw_payload['security']['ssh_root_login'] ?? 'no' }}</span></div>
                </div>
            </div>

            <!-- Failed Services Card -->
            <div class="bg-dark-800 border border-dark-600/60 rounded-xl p-4 shadow">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block mb-1">Failed Units</span>
                <div class="flex items-baseline gap-2 mt-1">
                    <span class="text-xl font-bold font-mono {{ $latestAudit->failed_services_count > 0 ? 'text-rose-400' : 'text-emerald-400' }}">
                        {{ $latestAudit->failed_services_count }}
                    </span>
                    <span class="text-xs text-slate-500">Systemd units</span>
                </div>
            </div>

            <!-- Listening Ports Card -->
            <div class="bg-dark-800 border border-dark-600/60 rounded-xl p-4 shadow">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block mb-1">Listening Sockets</span>
                <div class="flex items-baseline gap-2 mt-1">
                    <span class="text-xl font-bold font-mono text-white">
                        {{ count($latestAudit->raw_payload['network']['listening_ports'] ?? []) }}
                    </span>
                    <span class="text-xs text-slate-500">TCP/UDP ports</span>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Historical Security Score Chart -->
            <div class="lg:col-span-2 bg-dark-800 border border-dark-600/70 rounded-2xl p-6 shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-slate-300">Security Score History (Trend)</h2>
                    <span class="text-xs text-slate-500 font-mono">Last 15 Audits</span>
                </div>
                <div class="h-64">
                    <canvas id="scoreHistoryChart"></canvas>
                </div>
            </div>

            <!-- Findings Severity Distribution (Doughnut) -->
            <div class="bg-dark-800 border border-dark-600/70 rounded-2xl p-6 shadow-xl flex flex-col justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-300 mb-2">Findings Severity</h2>
                <div class="h-52 relative flex items-center justify-center">
                    <canvas id="severityDonutChart"></canvas>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs font-mono mt-4 pt-4 border-t border-dark-600/50">
                    <div class="flex items-center gap-1.5 text-rose-400">
                        <span>🔴 CRITICAL:</span> <strong class="text-white">{{ $severityCounts['CRITICAL'] }}</strong>
                    </div>
                    <div class="flex items-center gap-1.5 text-orange-400">
                        <span>🟠 HIGH:</span> <strong class="text-white">{{ $severityCounts['HIGH'] }}</strong>
                    </div>
                    <div class="flex items-center gap-1.5 text-amber-400">
                        <span>🟡 MODERATE:</span> <strong class="text-white">{{ $severityCounts['MODERATE'] }}</strong>
                    </div>
                    <div class="flex items-center gap-1.5 text-emerald-400">
                        <span>🟢 LOW:</span> <strong class="text-white">{{ $severityCounts['LOW'] }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resource Telemetry Trend Chart -->
        <div class="bg-dark-800 border border-dark-600/70 rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-300">System Resource Utilization Trend (%)</h2>
                <span class="text-xs text-slate-500 font-mono">CPU &bull; RAM &bull; Disk</span>
            </div>
            <div class="h-60">
                <canvas id="resourceTrendChart"></canvas>
            </div>
        </div>

        <!-- Security Findings Table (Detailed Breakdown) -->
        <div class="bg-dark-800 border border-dark-600/70 rounded-2xl shadow-xl overflow-hidden" x-data="{ expanded: null }">
            <div class="p-5 border-b border-dark-600/60 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h2 class="text-base font-bold text-white tracking-tight">Security Findings & Recommendations</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-mono bg-dark-700 text-purple-accent border border-purple/20">
                        {{ $recentFindings->count() }} Issues
                    </span>
                </div>
                <span class="text-xs text-slate-400 hidden sm:inline">Click any finding to inspect evidence and remediation</span>
            </div>

            @if($recentFindings->isEmpty())
                <div class="p-8 text-center text-slate-400">
                    <svg class="w-10 h-10 text-emerald-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <p class="text-sm font-medium text-slate-200">No active security findings detected.</p>
                    <p class="text-xs text-slate-500 mt-1">This server aligns with standard security baselines.</p>
                </div>
            @else
                <div class="divide-y divide-dark-600/40">
                    @foreach($recentFindings as $index => $finding)
                        <div class="p-5 hover:bg-dark-700/40 transition-colors cursor-pointer"
                             @click="expanded = (expanded === {{ $index }} ? null : {{ $index }})">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-3">
                                    <span class="text-lg flex-shrink-0 mt-0.5">{{ $finding->severity_icon }}</span>
                                    <div>
                                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                                            <span class="font-mono text-xs font-bold text-purple-glow px-2 py-0.5 rounded bg-dark-900 border border-dark-600">
                                                {{ $finding->finding_code }}
                                            </span>
                                            <span class="text-xs font-semibold px-2 py-0.5 rounded border {{ $finding->severity_badge_color }}">
                                                {{ $finding->severity }}
                                            </span>
                                            <span class="text-xs font-mono text-slate-400">
                                                [{{ $finding->category }}]
                                            </span>
                                            <span class="text-xs font-mono text-rose-400">
                                                -{{ $finding->score_impact }} pts
                                            </span>
                                        </div>
                                        <h3 class="text-sm font-semibold text-white tracking-wide">
                                            {{ $finding->title }}
                                        </h3>
                                    </div>
                                </div>
                                <div class="text-slate-400 hover:text-white transition-transform duration-200 flex-shrink-0"
                                     :class="expanded === {{ $index }} ? 'rotate-180' : ''">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                            </div>

                            <!-- Expandable Finding Details -->
                            <div x-show="expanded === {{ $index }}" x-collapse class="mt-4 pt-4 border-t border-dark-600/50 space-y-3 text-xs">
                                @if($finding->evidence)
                                    <div>
                                        <span class="text-[11px] uppercase tracking-wider font-semibold text-slate-400 block mb-1">Discovered Evidence:</span>
                                        <div class="p-2.5 bg-dark-900 rounded-lg font-mono text-slate-200 border border-dark-600 overflow-x-auto">
                                            {{ $finding->evidence }}
                                        </div>
                                    </div>
                                @endif

                                @if($finding->description)
                                    <div>
                                        <span class="text-[11px] uppercase tracking-wider font-semibold text-slate-400 block mb-1">Risk Explanation:</span>
                                        <p class="text-slate-300 leading-relaxed">{{ $finding->description }}</p>
                                    </div>
                                @endif

                                @if($finding->recommendation)
                                    <div class="p-3 bg-purple/10 border border-purple/25 rounded-xl">
                                        <span class="text-[11px] uppercase tracking-wider font-semibold text-purple-glow block mb-1">Remediation Guide:</span>
                                        <p class="text-slate-200 leading-relaxed">{{ $finding->recommendation }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const labels = {!! json_encode($chartLabels) !!};
        const scores = {!! json_encode($scoreSeries) !!};
        const cpuData = {!! json_encode($cpuSeries) !!};
        const memData = {!! json_encode($memSeries) !!};
        const diskData = {!! json_encode($diskSeries) !!};

        // 1. Score History Chart
        const scoreCtx = document.getElementById('scoreHistoryChart');
        if (scoreCtx && scores.length > 0) {
            new Chart(scoreCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Security Score',
                        data: scores,
                        borderColor: '#a855f7',
                        backgroundColor: 'rgba(168, 85, 247, 0.1)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#a855f7',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            min: 0,
                            max: 100,
                            grid: { color: 'rgba(44, 50, 71, 0.4)' },
                            ticks: { color: '#94a3b8', font: { family: 'JetBrains Mono', size: 11 } }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#94a3b8', font: { family: 'JetBrains Mono', size: 10 } }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#151824',
                            titleColor: '#f8fafc',
                            bodyColor: '#c084fc',
                            borderColor: '#2c3247',
                            borderWidth: 1,
                        }
                    }
                }
            });
        }

        // 2. Severity Donut Chart
        const donutCtx = document.getElementById('severityDonutChart');
        if (donutCtx) {
            new Chart(donutCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Critical', 'High', 'Moderate', 'Low'],
                    datasets: [{
                        data: [
                            {{ $severityCounts['CRITICAL'] }},
                            {{ $severityCounts['HIGH'] }},
                            {{ $severityCounts['MODERATE'] }},
                            {{ $severityCounts['LOW'] }}
                        ],
                        backgroundColor: ['#ef4444', '#f97316', '#f59e0b', '#10b981'],
                        borderColor: '#0e1017',
                        borderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }

        // 3. Resource Trend Chart
        const resCtx = document.getElementById('resourceTrendChart');
        if (resCtx && cpuData.length > 0) {
            new Chart(resCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'CPU %',
                            data: cpuData,
                            borderColor: '#38bdf8',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            tension: 0.3
                        },
                        {
                            label: 'RAM %',
                            data: memData,
                            borderColor: '#a855f7',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            tension: 0.3
                        },
                        {
                            label: 'Disk %',
                            data: diskData,
                            borderColor: '#f59e0b',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            min: 0,
                            max: 100,
                            grid: { color: 'rgba(44, 50, 71, 0.4)' },
                            ticks: { color: '#94a3b8', font: { family: 'JetBrains Mono', size: 10 } }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#94a3b8', font: { family: 'JetBrains Mono', size: 10 } }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: { color: '#cbd5e1', font: { family: 'Plus Jakarta Sans', size: 11 } }
                        }
                    }
                }
            });
        }
    });
</script>
@endpush
