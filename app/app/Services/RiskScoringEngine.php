<?php

namespace App\Services;

class RiskScoringEngine
{
    /**
     * Evaluate incoming telemetry and findings, verifying and normalizing scores.
     *
     * @param array $payload
     * @return array{score: int, risk_level: string, findings: array}
     */
    public function evaluate(array $payload): array
    {
        $baseScore = 100;
        $findings = $payload['findings'] ?? [];
        $normalizedFindings = [];

        foreach ($findings as $f) {
            $impact = (int) ($f['score_impact'] ?? 0);
            $baseScore -= $impact;

            $normalizedFindings[] = [
                'finding_code'   => $f['id'] ?? 'GEN-001',
                'category'       => strtoupper($f['category'] ?? 'GENERAL'),
                'severity'       => strtoupper($f['severity'] ?? 'LOW'),
                'score_impact'   => $impact,
                'title'          => $f['title'] ?? 'Security observation',
                'evidence'       => $f['evidence'] ?? null,
                'description'    => $f['description'] ?? null,
                'recommendation' => $f['recommendation'] ?? null,
            ];
        }

        // Bound between 0 and 100
        $finalScore = max(0, min(100, $baseScore));

        // Determine Risk Level Category
        $riskLevel = match (true) {
            $finalScore >= 90 => 'LOW',
            $finalScore >= 75 => 'MODERATE',
            $finalScore >= 50 => 'HIGH',
            default           => 'CRITICAL',
        };

        return [
            'score'      => $finalScore,
            'risk_level' => $riskLevel,
            'findings'   => $normalizedFindings,
        ];
    }
}
