<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $query = Audit::with(['server', 'findings'])->orderBy('audited_at', 'desc');

        if ($request->filled('server_id')) {
            $query->where('server_id', $request->server_id);
        }

        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->risk_level);
        }

        $audits = $query->paginate(15)->withQueryString();
        $servers = Server::orderBy('name')->get();

        return view('audits.index', compact('audits', 'servers'));
    }

    public function show(int $id)
    {
        $audit = Audit::with(['server', 'findings', 'aiReport', 'notifications'])->findOrFail($id);

        return view('audits.show', compact('audit'));
    }

    public function triggerManual(Request $request)
    {
        $n8nWebhookUrl = config('sentinel.n8n_webhook_url');
        $serverId = $request->input('server_id');

        try {
            $response = Http::timeout(10)->post($n8nWebhookUrl, [
                'action'     => 'manual_trigger',
                'server_id'  => $serverId,
                'triggered_by' => auth()->user()?->email ?? 'admin',
                'timestamp'  => now()->toISOString(),
            ]);

            if ($response->successful()) {
                return redirect()->back()->with('success', 'Manual audit requested successfully. n8n automation pipeline initiated.');
            }

            return redirect()->back()->with('warning', 'Audit triggered, but n8n returned status: ' . $response->status());
        } catch (\Throwable $e) {
            Log::error("Manual audit trigger failed: " . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to reach n8n webhook: ' . $e->getMessage());
        }
    }
}
