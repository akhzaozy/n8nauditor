@extends('layouts.app', ['title' => 'Settings & AI Integration'])

@section('content')
<div class="space-y-6">

    <div class="pb-2 border-b border-dark-600/40">
        <h1 class="text-2xl font-black tracking-tight text-white uppercase font-sans">Settings & AI Integration</h1>
        <p class="text-xs text-slate-400 mt-1">Configure modular AI reasoning engines, automation webhooks, and security constraints.</p>
    </div>

    <!-- AI Connectivity Test Result Alert (if triggered) -->
    @if(session('ai_result'))
        <div class="bg-dark-800 border border-purple-accent/50 rounded-2xl p-6 shadow-2xl space-y-3">
            <div class="flex items-center justify-between">
                <span class="inline-flex items-center gap-2 text-xs font-bold text-purple-glow uppercase tracking-wider">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span> AI Diagnostics Test Output
                </span>
                <span class="text-xs font-mono text-slate-400">
                    Provider: {{ session('ai_result')['provider'] }} &bull; Model: {{ session('ai_result')['model'] }}
                </span>
            </div>
            <div class="p-3 bg-dark-900 rounded-xl text-xs text-slate-200 border border-dark-600">
                <strong>Executive Summary:</strong> {{ session('ai_result')['summary'] }}
            </div>
            <div class="p-3 bg-dark-900 rounded-xl text-xs text-slate-300 border border-dark-600">
                <strong>Strategic Assessment:</strong> {{ session('ai_result')['overall_assessment'] }}
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- AI Engine Configuration Card -->
        <div class="bg-dark-800 border border-dark-600/70 rounded-2xl p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-dark-600/60">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-purple/20 flex items-center justify-center text-purple-accent">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-white">AI Reasoner Layer</h2>
                </div>
                <span class="text-[11px] font-mono uppercase px-2 py-0.5 rounded bg-dark-700 text-purple-glow border border-dark-600">
                    {{ $aiConfig['provider'] }}
                </span>
            </div>

            <p class="text-xs text-slate-300 leading-relaxed">
                SentinelAI utilizes a modular LLM abstraction layer. AI models receive pre-evaluated rule findings in structured JSON and generate human-readable operational risk summaries.
            </p>

            <div class="space-y-2 text-xs font-mono bg-dark-900 p-4 rounded-xl border border-dark-600 text-slate-300">
                <div class="flex justify-between">
                    <span class="text-slate-500">Provider:</span>
                    <span class="text-white">{{ $aiConfig['provider'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Gateway URL:</span>
                    <span class="text-white truncate max-w-xs">{{ $aiConfig['base_url'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Active Model:</span>
                    <span class="text-purple-glow font-bold">{{ $aiConfig['model'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">API Key Configured:</span>
                    <span class="{{ $aiConfig['has_key'] ? 'text-emerald-400 font-bold' : 'text-amber-400' }}">
                        {{ $aiConfig['has_key'] ? 'Configured (Active)' : 'Unset (Using Local / Fallback)' }}
                    </span>
                </div>
            </div>

            <div class="pt-2">
                <form action="{{ route('settings.test-ai') }}" method="POST">
                    @csrf
                    <button type="submit" 
                            class="w-full py-2.5 px-4 rounded-xl bg-purple-accent/15 hover:bg-purple-accent/25 border border-purple-accent/30 text-purple-glow text-xs font-semibold transition-all flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Test AI Connectivity & Reasoning
                    </button>
                </form>
            </div>
        </div>

        <!-- Automation & Security Policy Card -->
        <div class="bg-dark-800 border border-dark-600/70 rounded-2xl p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-dark-600/60">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-white">Security & Automation</h2>
                </div>
                <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                    Enforced
                </span>
            </div>

            <div class="space-y-3 text-xs text-slate-300">
                <div class="p-3 bg-dark-900 rounded-xl border border-dark-600/70 space-y-1">
                    <strong class="text-white block font-sans">Zero Shell Access Guarantee</strong>
                    <p class="text-slate-400 leading-relaxed">
                        AI models have NO interactive shell or execution bridge. The collector executes strictly read-only commands without root-level write mutations.
                    </p>
                </div>

                <div class="p-3 bg-dark-900 rounded-xl border border-dark-600/70 space-y-1">
                    <strong class="text-white block font-sans">n8n Automation Pipeline</strong>
                    <div class="font-mono text-[11px] text-slate-400 truncate">
                        Webhook: <span class="text-slate-200">{{ $n8nWebhookUrl }}</span>
                    </div>
                </div>

                <div class="p-3 bg-dark-900 rounded-xl border border-dark-600/70 space-y-1">
                    <strong class="text-white block font-sans">System Environment</strong>
                    <div class="grid grid-cols-2 gap-1 font-mono text-[11px] text-slate-400">
                        <div>PHP: {{ $systemInfo['php_version'] }}</div>
                        <div>Laravel: {{ $systemInfo['laravel_version'] }}</div>
                        <div>Timezone: {{ $systemInfo['timezone'] }}</div>
                        <div>Architecture: x86_64 / arm64</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
