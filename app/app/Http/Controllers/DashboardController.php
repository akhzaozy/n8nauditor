<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\Server;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $servers = Server::orderBy('name')->get();
        $serverId = $request->query('server_id', $servers->first()?->id);

        $selectedServer = $servers->firstWhere('id', $serverId);
        
        $latestAudit = null;
        $recentFindings = collect();
        $historicalAudits = collect();
        $severityCounts = ['CRITICAL' => 0, 'HIGH' => 0, 'MODERATE' => 0, 'LOW' => 0];

        if ($selectedServer) {
            $latestAudit = $selectedServer->audits()->with(['findings', 'aiReport'])->first();

            $historicalAudits = $selectedServer->audits()
                ->orderBy('audited_at', 'asc')
                ->take(15)
                ->get();

            if ($latestAudit) {
                $recentFindings = $latestAudit->findings;
                
                // Calculate severity distribution for latest audit
                foreach ($latestAudit->findings as $finding) {
                    $sev = strtoupper($finding->severity);
                    if (isset($severityCounts[$sev])) {
                        $severityCounts[$sev]++;
                    }
                }
            }
        }

        // Global stats
        $totalServers = $servers->count();
        $totalAuditsCount = Audit::count();
        $criticalFindingsCount = AuditFinding::where('severity', 'CRITICAL')->count();

        // Chart data preparation
        $chartLabels = $historicalAudits->map(fn($a) => $a->audited_at->format('M d H:i'))->toArray();
        $scoreSeries = $historicalAudits->pluck('score')->toArray();
        $cpuSeries   = $historicalAudits->pluck('cpu_usage')->toArray();
        $memSeries   = $historicalAudits->pluck('memory_usage')->toArray();
        $diskSeries  = $historicalAudits->pluck('disk_usage')->toArray();

        return view('dashboard.index', compact(
            'servers',
            'selectedServer',
            'latestAudit',
            'recentFindings',
            'severityCounts',
            'totalServers',
            'totalAuditsCount',
            'criticalFindingsCount',
            'chartLabels',
            'scoreSeries',
            'cpuSeries',
            'memSeries',
            'diskSeries'
        ));
    }
}
