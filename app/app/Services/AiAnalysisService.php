<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAnalysisService
{
    protected string $provider;
    protected string $baseUrl;
    protected string $apiKey;
    protected string $model;
    protected int $timeout;
    protected string $systemPrompt;

    public function __construct()
    {
        $this->provider     = config('sentinel.ai.provider', '9router');
        $this->baseUrl      = rtrim(config('sentinel.ai.base_url', 'https://api.9router.com/v1'), '/');
        $this->apiKey       = config('sentinel.ai.api_key', '');
        $this->model        = config('sentinel.ai.model', 'deepseek-chat');
        $this->timeout      = (int) config('sentinel.ai.timeout', 30);
        $this->systemPrompt = config('sentinel.ai.system_prompt', '');
    }

    /**
     * Analyze audit findings using configured AI provider.
     *
     * @param array $auditData (server telemetry, score, findings)
     * @return array
     */
    public function analyze(array $auditData): array
    {
        $hostname  = $auditData['server']['hostname'] ?? 'Unknown Host';
        $os        = $auditData['server']['os'] ?? 'Linux';
        $score     = $auditData['score'] ?? 100;
        $riskLevel = $auditData['risk_level'] ?? 'LOW';
        $findings  = $auditData['findings'] ?? [];

        // If no findings, return clean safe report
        if (empty($findings)) {
            return [
                'provider'           => $this->provider,
                'model'              => $this->model,
                'summary'            => "Server {$hostname} shows strong compliance with security baseline standards. No active high-risk misconfigurations were identified.",
                'overall_assessment' => "Posture is optimal ({$score}/100 - {$riskLevel}). Continue regular patch schedules and monitoring.",
                'findings_analysis'  => [],
                'raw_response'       => null,
            ];
        }

        // If no API key configured (and not local ollama), return rule-based synthetic assessment
        if (empty($this->apiKey) && $this->provider !== 'ollama') {
            return $this->generateRuleBasedFallback($auditData);
        }

        try {
            $userPrompt = "Server: {$hostname}\nOS: {$os}\nRisk Score: {$score}/100 ({$riskLevel})\n\nFindings Evidence:\n" .
                json_encode($findings, JSON_PRETTY_PRINT);

            $endpoint = $this->baseUrl . '/chat/completions';

            $headers = [
                'Content-Type' => 'application/json',
            ];
            if (!empty($this->apiKey)) {
                $headers['Authorization'] = 'Bearer ' . $this->apiKey;
            }

            $payload = [
                'model'       => $this->model,
                'messages'    => [
                    ['role' => 'system', 'content' => $this->systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
            ];

            $response = Http::withHeaders($headers)
                ->timeout($this->timeout)
                ->post($endpoint, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';

                // Strip markdown backticks if model wrapped in ```json
                $cleanContent = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($content));
                $parsed = json_decode($cleanContent, true);

                if (is_array($parsed)) {
                    return [
                        'provider'           => $this->provider,
                        'model'              => $this->model,
                        'summary'            => $parsed['summary'] ?? "Analysis completed for {$hostname}.",
                        'overall_assessment' => $parsed['overall_assessment'] ?? "Security risk level evaluated at {$riskLevel}.",
                        'findings_analysis'  => $parsed['findings'] ?? [],
                        'raw_response'       => $data,
                    ];
                }
            }

            Log::warning("AI Provider {$this->provider} returned non-200 or unparseable response: " . $response->body());
        } catch (\Throwable $e) {
            Log::error("AI Analysis failed with exception: " . $e->getMessage());
        }

        // Graceful fallback without breaking pipeline
        return $this->generateRuleBasedFallback($auditData);
    }

    /**
     * Generate synthetic rule-based risk narrative when AI provider is offline or unconfigured.
     */
    protected function generateRuleBasedFallback(array $auditData): array
    {
        $hostname  = $auditData['server']['hostname'] ?? 'Unknown Host';
        $score     = $auditData['score'] ?? 100;
        $riskLevel = $auditData['risk_level'] ?? 'LOW';
        $findings  = $auditData['findings'] ?? [];

        $count = count($findings);
        $summary = "Automated baseline evaluation for {$hostname}: {$count} security finding(s) detected with an aggregate score of {$score}/100 ({$riskLevel} risk).";
        
        $analysis = [];
        foreach ($findings as $f) {
            $analysis[] = [
                'id'             => $f['finding_code'] ?? $f['id'] ?? 'N/A',
                'explanation'    => $f['description'] ?? 'Identified during server inspection.',
                'impact'         => 'Increases potential attack surface or impacts system availability.',
                'recommendation' => $f['recommendation'] ?? 'Review system configuration and apply security hardening.',
                'priority'       => $f['severity'] ?? 'MODERATE',
            ];
        }

        return [
            'provider'           => $this->provider . ' (Rule Engine Fallback)',
            'model'              => $this->model,
            'summary'            => $summary,
            'overall_assessment' => "The server exhibits {$riskLevel} exposure. Addressing high-impact findings will restore score to baseline.",
            'findings_analysis'  => $analysis,
            'raw_response'       => null,
        ];
    }
}
