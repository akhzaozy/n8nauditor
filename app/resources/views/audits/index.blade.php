@extends('layouts.app', ['title' => 'Audit History'])

@section('content')
<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-dark-600/40">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-white uppercase font-sans">Audit History</h1>
            <p class="text-xs text-slate-400 mt-1">Review historical security posture evaluations and compliance snapshots.</p>
        </div>

        <form method="GET" action="{{ route('audits.index') }}" class="flex flex-wrap items-center gap-3">
            <select name="server_id" onchange="this.form.submit()" class="bg-dark-800 border border-dark-600 text-slate-200 text-xs rounded-xl px-3 py-2">
                <option value="">All Servers</option>
                @foreach($servers as $s)
                    <option value="{{ $s->id }}" {{ request('server_id') == $s->id ? 'selected' : '' }}>{{ $s->hostname }}</option>
                @endforeach
            </select>

            <select name="risk_level" onchange="this.form.submit()" class="bg-dark-800 border border-dark-600 text-slate-200 text-xs rounded-xl px-3 py-2">
                <option value="">All Risk Levels</option>
                <option value="CRITICAL" {{ request('risk_level') === 'CRITICAL' ? 'selected' : '' }}>Critical</option>
                <option value="HIGH" {{ request('risk_level') === 'HIGH' ? 'selected' : '' }}>High</option>
                <option value="MODERATE" {{ request('risk_level') === 'MODERATE' ? 'selected' : '' }}>Moderate</option>
                <option value="LOW" {{ request('risk_level') === 'LOW' ? 'selected' : '' }}>Low</option>
            </select>
        </form>
    </div>

    <!-- Audits Table -->
    <div class="bg-dark-800 border border-dark-600/70 rounded-2xl shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-dark-700/60 border-b border-dark-600/50 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="py-3.5 px-5">Audit ID</th>
                        <th class="py-3.5 px-5">Target Server</th>
                        <th class="py-3.5 px-5">Score</th>
                        <th class="py-3.5 px-5">Risk Level</th>
                        <th class="py-3.5 px-5">Resource (CPU / RAM / Disk)</th>
                        <th class="py-3.5 px-5">Findings</th>
                        <th class="py-3.5 px-5">Audited At</th>
                        <th class="py-3.5 px-5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-dark-600/40 font-mono">
                    @forelse($audits as $audit)
                        <tr class="hover:bg-dark-700/30 transition-colors">
                            <td class="py-4 px-5 text-purple-glow font-bold">#{{ $audit->id }}</td>
                            <td class="py-4 px-5 font-sans font-semibold text-white">
                                {{ $audit->server->hostname ?? 'N/A' }}
                                <span class="block text-[11px] font-normal text-slate-400">{{ $audit->server->name ?? '' }}</span>
                            </td>
                            <td class="py-4 px-5">
                                <span class="text-sm font-bold" style="color: {{ $audit->score_hex_color }}">{{ $audit->score }}/100</span>
                            </td>
                            <td class="py-4 px-5">
                                <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-bold border {{ $audit->risk_badge_color }}">
                                    {{ $audit->risk_level }}
                                </span>
                            </td>
                            <td class="py-4 px-5 text-slate-300">
                                {{ $audit->cpu_usage ?? 0 }}% / {{ $audit->memory_usage ?? 0 }}% / {{ $audit->disk_usage ?? 0 }}%
                            </td>
                            <td class="py-4 px-5">
                                <span class="text-slate-200 font-semibold">{{ $audit->findings->count() }}</span> issues
                            </td>
                            <td class="py-4 px-5 text-slate-400 font-sans text-[11px]">
                                {{ $audit->audited_at->format('Y-m-d H:i') }}
                                <span class="block text-slate-500 font-mono">{{ $audit->audited_at->diffForHumans() }}</span>
                            </td>
                            <td class="py-4 px-5 text-right font-sans">
                                <a href="{{ route('audits.show', $audit->id) }}" 
                                   class="px-3 py-1.5 rounded-lg bg-dark-700 hover:bg-purple/20 hover:text-purple-accent text-slate-200 font-medium text-xs transition-colors border border-dark-600">
                                    Details &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-400 font-sans">
                                No audit records match the selected criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($audits->hasPages())
            <div class="p-4 border-t border-dark-600/50">
                {{ $audits->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
