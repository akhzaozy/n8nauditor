<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SentinelAI Core Ingest Secret
    |--------------------------------------------------------------------------
    | Token verified on X-Sentinel-Token header for collector & n8n webhooks.
    */
    'api_secret' => env('SENTINEL_API_SECRET', 'sentinel-secret-token-change-me'),

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration (Modular)
    |--------------------------------------------------------------------------
    | Supported: '9router', 'openai', 'ollama'
    | All providers adhere to OpenAI-compatible chat completion schema.
    */
    'ai' => [
        'provider' => env('AI_PROVIDER', '9router'),
        'base_url' => env('AI_BASE_URL', 'https://api.9router.com/v1'),
        'api_key'  => env('AI_API_KEY', ''),
        'model'    => env('AI_MODEL', 'deepseek-chat'),
        'timeout'  => (int) env('AI_TIMEOUT', 30),

        // Strict system prompt enforcing read-only interpretation & zero execution
        'system_prompt' => <<<PROMPT
You are SentinelAI, an automated server security auditor.
Your job is to analyze Linux system security audit findings strictly based on provided facts and evidence.

STRICT CONSTRAINTS:
1. You have ZERO shell or command execution access. Never simulate shell prompts or execute commands.
2. Rely strictly on provided evidence. Do NOT hallucinate vulnerabilities or misconfigurations not supported by evidence.
3. NEVER suggest destructive commands (e.g., rm -rf, mkfs, iptables -F, drop table, rebooting without warning).
4. All recommendations must follow the principle of least privilege, defense-in-depth, and operational safety.

OUTPUT SCHEMA:
You must output ONLY valid JSON without markdown fences with the following structure:
{
  "summary": "Concise executive overview of server posture (2-3 sentences)",
  "overall_assessment": "Strategic risk assessment and exposure level",
  "findings": [
    {
      "id": "Matching Finding ID from evidence (e.g. SSH-001)",
      "explanation": "Clear explanation of why this finding poses a security risk",
      "impact": "Realistic operational and security impact if exploited",
      "recommendation": "Step-by-step safe remediation guide",
      "priority": "CRITICAL | HIGH | MODERATE | LOW"
    }
  ]
}
PROMPT
    ],

    /*
    |--------------------------------------------------------------------------
    | Risk Scoring Bands (0 - 100)
    |--------------------------------------------------------------------------
    */
    'scoring' => [
        'critical' => [
            'min' => 0,
            'max' => 49,
            'label' => 'CRITICAL',
            'color' => '#ef4444',
        ],
        'high' => [
            'min' => 50,
            'max' => 74,
            'label' => 'HIGH',
            'color' => '#f97316',
        ],
        'moderate' => [
            'min' => 75,
            'max' => 89,
            'label' => 'MODERATE',
            'color' => '#f59e0b',
        ],
        'low' => [
            'min' => 90,
            'max' => 100,
            'label' => 'LOW',
            'color' => '#10b981',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | n8n Webhook Endpoint
    |--------------------------------------------------------------------------
    */
    'n8n_webhook_url' => env('N8N_WEBHOOK_URL', 'http://n8n:5678/webhook/sentinel-audit'),
];
