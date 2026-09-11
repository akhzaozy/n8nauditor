@extends('layouts.app', ['title' => 'Server Nodes'])

@section('content')
<div class="space-y-6" x-data="{ createModal: false }">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-dark-600/40">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-white uppercase font-sans">Server Inventory</h1>
            <p class="text-xs text-slate-400 mt-1">Manage audited Linux server nodes, API tokens, and agent deploy commands.</p>
        </div>

        <button @click="createModal = true" 
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-purple-accent hover:bg-purple text-white text-xs font-semibold shadow-lg shadow-purple/20 transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Register New Server
        </button>
    </div>

    <!-- Servers Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($servers as $server)
            <div class="bg-dark-800 border border-dark-600/70 rounded-2xl p-6 shadow-xl flex flex-col justify-between space-y-4">
                <div>
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div>
                            <h2 class="text-base font-bold text-white">{{ $server->name }}</h2>
                            <span class="font-mono text-xs text-purple-glow">{{ $server->hostname }}</span>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold uppercase {{ $server->status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 'bg-slate-700 text-slate-400' }}">
                            {{ $server->status }}
                        </span>
                    </div>

                    <p class="text-xs text-slate-400 mb-4 line-clamp-2">{{ $server->description ?? 'No description provided.' }}</p>

                    <div class="space-y-1.5 text-xs font-mono bg-dark-900/60 p-3 rounded-xl border border-dark-600/60 text-slate-300">
                        <div class="flex justify-between">
                            <span class="text-slate-500">IP:</span>
                            <span>{{ $server->ip_address ?? 'Auto-detect' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">OS:</span>
                            <span class="truncate max-w-[160px]" title="{{ $server->os_info }}">{{ $server->os_info ?? 'Linux' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Last Audit:</span>
                            <span>{{ $server->last_audited_at ? $server->last_audited_at->diffForHumans() : 'Never' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Agent Quick Command -->
                <div class="pt-3 border-t border-dark-600/50 space-y-2">
                    <div class="flex items-center justify-between text-[11px] text-slate-400">
                        <span>Agent Ingest Token</span>
                        <form action="{{ route('servers.token', $server->id) }}" method="POST" onsubmit="return confirm('Regenerate API token? Old token will be invalidated.')">
                            @csrf
                            <button type="submit" class="text-purple-accent hover:underline">Regenerate</button>
                        </form>
                    </div>
                    <div class="p-2 bg-dark-900 rounded-lg text-[11px] font-mono text-slate-300 border border-dark-600 flex items-center justify-between">
                        <span class="truncate">{{ $server->api_token }}</span>
                        <button type="button" @click="navigator.clipboard.writeText('{{ $server->api_token }}'); alert('Token copied!')" 
                                class="text-purple-accent hover:text-white pl-2">
                            Copy
                        </button>
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <a href="{{ route('dashboard', ['server_id' => $server->id]) }}" class="text-xs font-medium text-purple-accent hover:underline">
                            Open Dashboard &rarr;
                        </a>

                        <form action="{{ route('servers.destroy', $server->id) }}" method="POST" onsubmit="return confirm('Delete this server and all its audits?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-rose-400/80 hover:text-rose-400">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-dark-800 border border-dark-600 rounded-2xl p-12 text-center text-slate-400">
                No server nodes registered. Click "Register New Server" above to add your first node.
            </div>
        @endforelse
    </div>

    <!-- Register Modal -->
    <div x-show="createModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
        
        <div @click.away="createModal = false" class="bg-dark-800 border border-dark-600 rounded-2xl p-6 w-full max-w-lg shadow-2xl">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-dark-600/60">
                <h3 class="text-base font-bold text-white uppercase tracking-wider">Register Server Node</h3>
                <button @click="createModal = false" class="text-slate-400 hover:text-white">&times;</button>
            </div>

            <form action="{{ route('servers.store') }}" method="POST" class="space-y-4 text-xs">
                @csrf
                <div>
                    <label class="block font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Server Display Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Production Web Core"
                           class="w-full px-3 py-2 bg-dark-900 border border-dark-600 rounded-xl text-white focus:outline-none focus:border-purple-accent">
                </div>

                <div>
                    <label class="block font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Hostname *</label>
                    <input type="text" name="hostname" required placeholder="e.g. srv-prod-01"
                           class="w-full px-3 py-2 bg-dark-900 border border-dark-600 rounded-xl text-white font-mono focus:outline-none focus:border-purple-accent">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold uppercase tracking-wider text-slate-300 mb-1.5">IP Address</label>
                        <input type="text" name="ip_address" placeholder="192.168.1.50"
                               class="w-full px-3 py-2 bg-dark-900 border border-dark-600 rounded-xl text-white font-mono focus:outline-none focus:border-purple-accent">
                    </div>
                    <div>
                        <label class="block font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Operating System</label>
                        <input type="text" name="os_info" placeholder="Ubuntu 24.04 LTS"
                               class="w-full px-3 py-2 bg-dark-900 border border-dark-600 rounded-xl text-white focus:outline-none focus:border-purple-accent">
                    </div>
                </div>

                <div>
                    <label class="block font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Description</label>
                    <textarea name="description" rows="2" placeholder="Primary web node hosting application stack..."
                              class="w-full px-3 py-2 bg-dark-900 border border-dark-600 rounded-xl text-white focus:outline-none focus:border-purple-accent"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-dark-600/60">
                    <button type="button" @click="createModal = false" class="px-4 py-2 rounded-xl bg-dark-700 hover:bg-dark-600 text-slate-300 font-semibold">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-purple-accent hover:bg-purple text-white font-semibold">
                        Register Node
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
