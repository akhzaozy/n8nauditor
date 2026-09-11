<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiReport;
use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\Server;
use App\Services\AiAnalysisService;
use App\Services\NotificationService;
use App\Services\RiskScoringEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditIngestController extends Controller
{
    public function __construct(
        protected RiskScoringEngine $scoringEngine,
        protected AiAnalysisService $aiService,
        protected NotificationService $notificationService
    ) {}

    public function ingest(Request $request): JsonResponse
    {
        // 1. Authenticate Request
        $token = $request->header('X-Sentinel-Token') ?? $request->bearerToken();
        $expectedSecret = config('sentinel.api_secret');

        $server = null;
        if (!empty($token)) {
            // Check if matches server token or master secret
            if ($token !== $expectedSecret) {
                $server = Server::where('api_token', $token)->first();
                if (!$server) {
                    return response()->json(['error' => 'Unauthorized: Invalid Sentinel token'], 401);
                }
            }
        } else {
            return response()->json(['error' => 'Unauthorized: Missing X-Sentinel-Token header'], 401);
        }

        $payload = $request->all();
        if (empty($payload['server'])) {
            return response()->json(['error' => 'Invalid payload: missing server telemetry block'], 422);
        }

        $serverData = $payload['server'];
        $hostname = $serverData['hostname'] ?? 'unknown-server';

        // 2. Identify or auto-register server
        if (!$server) {
            $server = Server::firstOrCreate(
                ['hostname' => $hostname],
                [
                    'name'        => $hostname,
                    'os_info'     => $serverData['os'] ?? 'Linux',
                    'description' => 'Discovered by SentinelAI Collector',
                    'status'      => 'active',
                ]
            );
        } else {
            $server->update([
                'os_info' => $serverData['os'] ?? $server->os_info,
            ]);
        }

        // 3. Evaluate Risk Scoring
        $scoringResult = $this->scoringEngine->evaluate($payload);
        $score = $scoringResult['score'];
        $riskLevel = $scoringResult['risk_level'];
        $findings = $scoringResult['findings'];

        // Extract resource metrics
        $resources = $payload['resources'] ?? [];
        $security  = $payload['security'] ?? [];
        $services  = $payload['services'] ?? [];

        // 4. Save Audit Record
        $audit = DB::transaction(function () use ($server, $score, $riskLevel, $resources, $security, $services, $payload, $findings) {
            $audit = Audit::create([
                'server_id'             => $server->id,
                'score'                 => $score,
                'risk_level'            => $riskLevel,
                'cpu_usage'             => $resources['cpu_usage'] ?? null,
                'memory_usage'          => $resources['memory_usage'] ?? null,
                'disk_usage'            => $resources['disk_usage'] ?? null,
                'failed_services_count' => $services['failed'] ?? 0,
                'firewall_status'       => $security['firewall'] ?? 'unknown',
                'ssh_port'              => $security['ssh_port'] ?? '22',
                'raw_payload'           => $payload,
                'status'                => 'completed',
                'audited_at'            => now(),
            ]);

            // Save Findings
            foreach ($findings as $f) {
                AuditFinding::create([
                    'audit_id'       => $audit->id,
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

            $server->update(['last_audited_at' => now()]);

            return $audit;
        });

        // 5. AI Report Processing
        // If n8n already attached an AI report, use it; otherwise invoke AiAnalysisService
        if (!empty($payload['ai_report']) && is_array($payload['ai_report'])) {
            $aiData = $payload['ai_report'];
            AiReport::create([
                'audit_id'           => $audit->id,
                'provider'           => 'n8n-pipeline',
                'model'              => config('sentinel.ai.model', 'deepseek-chat'),
                'summary'            => $aiData['summary'] ?? null,
                'overall_assessment' => $aiData['overall_assessment'] ?? null,
                'findings_analysis'  => $aiData['findings'] ?? [],
                'raw_response'       => $aiData,
            ]);
        } else {
            // Run AI analysis
            $auditPayloadForAi = [
                'server'     => $serverData,
                'score'      => $score,
                'risk_level' => $riskLevel,
                'findings'   => $findings,
            ];

            $aiResult = $this->aiService->analyze($auditPayloadForAi);

            AiReport::create([
                'audit_id'           => $audit->id,
                'provider'           => $aiResult['provider'],
                'model'              => $aiResult['model'],
                'summary'            => $aiResult['summary'],
                'overall_assessment' => $aiResult['overall_assessment'],
                'findings_analysis'  => $aiResult['findings_analysis'],
                'raw_response'       => $aiResult['raw_response'],
            ]);
        }

        // 6. Dispatch Notifications
        $this->notificationService->dispatchAlerts($audit);

        return response()->json([
            'status'     => 'success',
            'audit_id'   => $audit->id,
            'server'     => $server->hostname,
            'score'      => $score,
            'risk_level' => $riskLevel,
            'findings'   => count($findings),
            'timestamp'  => $audit->audited_at->toIso8601String(),
        ], 201);
    }
}
