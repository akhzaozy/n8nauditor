<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServerController extends Controller
{
    public function index()
    {
        $servers = Server::with(['latestAudit'])->orderBy('name')->get();
        return view('servers.index', compact('servers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'hostname'    => ['required', 'string', 'max:255', 'unique:servers,hostname'],
            'ip_address'  => ['nullable', 'string', 'max:45'],
            'os_info'     => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['api_token'] = 'stk_' . Str::random(32);
        $validated['status'] = 'active';

        Server::create($validated);

        return redirect()->route('servers.index')->with('success', 'Server registered successfully.');
    }

    public function update(Request $request, Server $server)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'hostname'    => ['required', 'string', 'max:255', 'unique:servers,hostname,' . $server->id],
            'ip_address'  => ['nullable', 'string', 'max:45'],
            'os_info'     => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status'      => ['required', 'string', 'in:active,inactive,maintenance'],
        ]);

        $server->update($validated);

        return redirect()->route('servers.index')->with('success', 'Server details updated.');
    }

    public function regenerateToken(Server $server)
    {
        $server->update([
            'api_token' => 'stk_' . Str::random(32),
        ]);

        return redirect()->route('servers.index')->with('success', 'New API token generated for ' . $server->hostname);
    }

    public function destroy(Server $server)
    {
        $server->delete();
        return redirect()->route('servers.index')->with('success', 'Server removed.');
    }
}
