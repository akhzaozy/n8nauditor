<?php

namespace App\Http\Controllers;

use App\Services\AiAnalysisService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $aiConfig = [
            'provider'  => config('sentinel.ai.provider'),
            'base_url'  => config('sentinel.ai.base_url'),
            'model'     => config('sentinel.ai.model'),
            'has_key'   => !empty(config('sentinel.ai.api_key')),
        ];

        $n8nWebhookUrl = config('sentinel.n8n_webhook_url');
        $systemInfo = [
            'php_version'     => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Nginx / PHP-FPM',
            'timezone'        => config('app.timezone'),
        ];

        return view('settings.index', compact('aiConfig', 'n8nWebhookUrl', 'systemInfo'));
    }

    public function testAi(AiAnalysisService $aiService)
    {
        $dummyAudit = [
            'server' => [
                'hostname' => 'sentinel-test-node',
                'os'       => 'Ubuntu 24.04 LTS',
            ],
            'score'      => 85,
            'risk_level' => 'MODERATE',
            'findings'   => [
                [
                    'id'             => 'SSH-002',
                    'category'       => 'SSH',
                    'severity'       => 'HIGH',
                    'score_impact'   => 10,
                    'title'          => 'SSH password authentication enabled',
                    'evidence'       => 'PasswordAuthentication yes',
                    'description'    => 'Password authentication allowed in sshd_config.',
                    'recommendation' => 'Enforce key-based authentication.',
                ]
            ]
        ];

        $result = $aiService->analyze($dummyAudit);

        return redirect()->route('settings.index')->with([
            'success'   => 'AI connection test finished successfully!',
            'ai_result' => $result,
        ]);
    }
}
