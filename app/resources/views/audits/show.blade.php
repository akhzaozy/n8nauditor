@extends('layouts.app', ['title' => 'Audit #' . $audit->id])

@section('content')
<div class="space-y-6">

    <!-- Header & Back Button -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-dark-600/40">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('audits.index') }}" class="p-1.5 rounded-lg bg-dark-700 hover:bg-dark-600 text-slate-400 hover:text-white transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <h1 class="text-xl font-bold text-white tracking-tight">Audit Deep-Dive: <span class="text-purple-glow">#{{ $audit->id }}</span></h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-bold border {{ $audit->risk_badge_color }}">
                    {{ $audit->risk_level }}
                </span>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                Target: <strong class="text-slate-200">{{ $audit->server->hostname }}</strong> &bull; Audited on {{ $audit->audited_at->format('Y-m-d H:i:s T') }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <div class="text-right">
                <span class="text-xs text-slate-400 uppercase tracking-wider block">Security Score</span>
                <span class="text-2xl font-black" style="color: {{ $audit->score_hex_color }}">{{ $audit->score }} / 100</span>
            </div>
        </div>
    </div>

    <!-- AI Executive Report Card -->
    @if($audit->aiReport)
        <div class="bg-dark-800 border border-purple/30 rounded-2xl p-6 shadow-xl relative overflow-hidden">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-purple-accent animate-pulse"></span>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-purple-glow">AI Executive Risk Assessment</h2>
                </div>
                <span class="text-xs font-mono text-slate-400 bg-dark-900 px-2 py-1 rounded border border-dark-600">
                    {{ $audit->aiReport->provider }} &bull; {{ $audit->aiReport->model }}
                </span>
            </div>

            <div class="space-y-3 text-sm">
                <div>
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Executive Summary</h3>
                    <p class="text-slate-200 leading-relaxed">{{ $audit->aiReport->summary }}</p>
                </div>

                @if($audit->aiReport->overall_assessment)
                    <div class="p-3.5 rounded-xl bg-dark-900/80 border border-dark-600/70">
                        <h3 class="text-xs font-semibold text-purple-accent uppercase tracking-wider mb-1">Strategic Exposure Analysis</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">{{ $audit->aiReport->overall_assessment }}</p>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- Findings Section -->
    <div class="space-y-4">
        <h2 class="text-base font-bold text-white uppercase tracking-wider">Identified Security Findings ({{ $audit->findings->count() }})</h2>

        @forelse($audit->findings as $finding)
            <div class="bg-dark-800 border border-dark-600/70 rounded-2xl p-5 shadow-lg space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <span class="text-xl mt-0.5">{{ $finding->severity_icon }}</span>
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
                                <span class="text-xs font-mono text-rose-400 font-semibold">
                                    Impact: -{{ $finding->score_impact }} pts
                                </span>
                            </div>
                            <h3 class="text-base font-bold text-white">{{ $finding->title }}</h3>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs pt-2 border-t border-dark-600/40">
                    <div>
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block mb-1">Evidence Discovered:</span>
                        <div class="p-3 bg-dark-900 rounded-xl font-mono text-slate-200 border border-dark-600 overflow-x-auto">
                            {{ $finding->evidence ?? 'No evidence snippet available' }}
                        </div>
                    </div>

                    <div>
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block mb-1">Risk Impact:</span>
                        <p class="text-slate-300 leading-relaxed">{{ $finding->description ?? 'Increases attack exposure.' }}</p>
                    </div>
                </div>

                @if($finding->recommendation)
                    <div class="p-3.5 rounded-xl bg-purple/10 border border-purple/20 text-xs">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-purple-glow block mb-1">Remediation Action Plan:</span>
                        <p class="text-slate-200 leading-relaxed">{{ $finding->recommendation }}</p>
                    </div>
                @endif
            </div>
        @empty
            <div class="bg-dark-800 border border-dark-600 rounded-2xl p-8 text-center text-slate-400 text-sm">
                No security violations recorded for this audit.
            </div>
        @endforelse
    </div>

    <!-- Raw Telemetry Payload (Collapsible) -->
    <div class="bg-dark-800 border border-dark-600/70 rounded-2xl p-6 shadow-xl" x-data="{ openPayload: false }">
        <div class="flex items-center justify-between cursor-pointer" @click="openPayload = !openPayload">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-purple-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-300">Raw Telemetry & Inspection JSON</h3>
            </div>
            <button class="text-xs text-purple-accent font-medium hover:underline">
                <span x-text="openPayload ? 'Hide JSON' : 'View Full Sanitized JSON'"></span>
            </button>
        </div>

        <div x-show="openPayload" x-collapse class="mt-4 pt-4 border-t border-dark-600/50">
            <pre class="p-4 bg-dark-900 border border-dark-600 rounded-xl text-xs font-mono text-slate-300 overflow-x-auto max-h-96">{{ json_encode($audit->raw_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    </div>

</div>
@endsection
