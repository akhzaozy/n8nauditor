@extends('layouts.app', ['title' => 'Security Findings'])

@section('content')
<div class="space-y-6">

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-2 border-b border-dark-600/40">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-white uppercase font-sans">Security Findings Catalog</h1>
            <p class="text-xs text-slate-400 mt-1">Cross-server vulnerability and configuration defect inventory.</p>
        </div>

        <!-- Filters Form -->
        <form method="GET" action="{{ route('findings.index') }}" class="flex flex-wrap items-center gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search title or code..."
                   class="bg-dark-800 border border-dark-600 text-slate-200 text-xs rounded-xl px-3 py-2 w-48 focus:outline-none focus:border-purple-accent">

            <select name="severity" onchange="this.form.submit()" class="bg-dark-800 border border-dark-600 text-slate-200 text-xs rounded-xl px-3 py-2">
                <option value="">All Severities</option>
                <option value="CRITICAL" {{ request('severity') === 'CRITICAL' ? 'selected' : '' }}>🔴 Critical</option>
                <option value="HIGH" {{ request('severity') === 'HIGH' ? 'selected' : '' }}>🟠 High</option>
                <option value="MODERATE" {{ request('severity') === 'MODERATE' ? 'selected' : '' }}>🟡 Moderate</option>
                <option value="LOW" {{ request('severity') === 'LOW' ? 'selected' : '' }}>🟢 Low</option>
            </select>

            <select name="category" onchange="this.form.submit()" class="bg-dark-800 border border-dark-600 text-slate-200 text-xs rounded-xl px-3 py-2">
                <option value="">All Categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                @endforeach
            </select>

            <button type="submit" class="p-2 bg-dark-700 hover:bg-dark-600 text-slate-300 rounded-xl">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </button>
        </form>
    </div>

    <!-- Findings Table -->
    <div class="bg-dark-800 border border-dark-600/70 rounded-2xl shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-dark-700/60 border-b border-dark-600/50 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="py-3.5 px-5">Code</th>
                        <th class="py-3.5 px-5">Severity</th>
                        <th class="py-3.5 px-5">Category</th>
                        <th class="py-3.5 px-5">Finding Title</th>
                        <th class="py-3.5 px-5">Server</th>
                        <th class="py-3.5 px-5">Impact</th>
                        <th class="py-3.5 px-5 text-right">Audit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-dark-600/40">
                    @forelse($findings as $finding)
                        <tr class="hover:bg-dark-700/30 transition-colors">
                            <td class="py-4 px-5 font-mono font-bold text-purple-glow">{{ $finding->finding_code }}</td>
                            <td class="py-4 px-5">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded text-[11px] font-bold border {{ $finding->severity_badge_color }}">
                                    {{ $finding->severity_icon }} {{ $finding->severity }}
                                </span>
                            </td>
                            <td class="py-4 px-5 font-mono text-slate-400">{{ $finding->category }}</td>
                            <td class="py-4 px-5 font-semibold text-white max-w-md truncate" title="{{ $finding->title }}">
                                {{ $finding->title }}
                            </td>
                            <td class="py-4 px-5 font-sans text-slate-300">
                                {{ $finding->audit->server->hostname ?? 'N/A' }}
                            </td>
                            <td class="py-4 px-5 font-mono text-rose-400 font-bold">
                                -{{ $finding->score_impact }}
                            </td>
                            <td class="py-4 px-5 text-right font-sans">
                                <a href="{{ route('audits.show', $finding->audit_id) }}" class="text-purple-accent hover:underline font-mono">
                                    #{{ $finding->audit_id }} &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400 font-sans">
                                No security findings matched your filter parameters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($findings->hasPages())
            <div class="p-4 border-t border-dark-600/50">
                {{ $findings->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
