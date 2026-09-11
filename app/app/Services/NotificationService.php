<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Dispatch alert notifications if audit exceeds severity threshold.
     */
    public function dispatchAlerts(Audit $audit): void
    {
        // Only alert on HIGH or CRITICAL risk scores
        if (!in_array(strtoupper($audit->risk_level), ['HIGH', 'CRITICAL'])) {
            return;
        }

        $serverName = $audit->server->name ?? $audit->server->hostname ?? 'Production Server';
        $title = "🚨 SentinelAI Security Alert: {$serverName} [{$audit->risk_level}]";
        $findingsCount = $audit->findings()->count();
        $message = "Server security audit completed with score {$audit->score}/100 ({$audit->risk_level}). Identified {$findingsCount} finding(s). Review immediately.";

        // 1. Discord Webhook
        $discordWebhook = env('NOTIFICATION_DISCORD_WEBHOOK');
        if (!empty($discordWebhook)) {
            $this->sendDiscord($audit, $discordWebhook, $title, $message);
        }

        // 2. Telegram Bot
        $telegramToken  = env('NOTIFICATION_TELEGRAM_BOT_TOKEN');
        $telegramChatId = env('NOTIFICATION_TELEGRAM_CHAT_ID');
        if (!empty($telegramToken) && !empty($telegramChatId)) {
            $this->sendTelegram($audit, $telegramToken, $telegramChatId, $title, $message);
        }
    }

    protected function sendDiscord(Audit $audit, string $webhookUrl, string $title, string $message): void
    {
        try {
            $payload = [
                'embeds' => [
                    [
                        'title'       => $title,
                        'description' => $message,
                        'color'       => $audit->risk_level === 'CRITICAL' ? 15682884 : 16348950, // Red or Orange
                        'fields'      => [
                            ['name' => 'Score', 'value' => "{$audit->score} / 100", 'inline' => true],
                            ['name' => 'Risk Level', 'value' => $audit->risk_level, 'inline' => true],
                            ['name' => 'Firewall', 'value' => $audit->firewall_status ?? 'Unknown', 'inline' => true],
                        ],
                        'footer'      => ['text' => 'SentinelAI Security Auditor'],
                        'timestamp'   => now()->toISOString(),
                    ]
                ]
            ];

            $response = Http::timeout(5)->post($webhookUrl, $payload);
            Notification::create([
                'audit_id'         => $audit->id,
                'channel'          => 'discord',
                'title'            => $title,
                'message'          => $message,
                'status'           => $response->successful() ? 'sent' : 'failed',
                'response_payload' => $response->body(),
                'sent_at'          => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Discord notification error: " . $e->getMessage());
        }
    }

    protected function sendTelegram(Audit $audit, string $token, string $chatId, string $title, string $message): void
    {
        try {
            $url = "https://api.telegram.org/bot{$token}/sendMessage";
            $text = "<b>{$title}</b>\n\n{$message}\n\n<b>Score:</b> {$audit->score}/100\n<b>Risk:</b> {$audit->risk_level}";

            $response = Http::timeout(5)->post($url, [
                'chat_id'    => $chatId,
                'text'       => $text,
                'parse_mode' => 'HTML',
            ]);

            Notification::create([
                'audit_id'         => $audit->id,
                'channel'          => 'telegram',
                'title'            => $title,
                'message'          => $message,
                'status'           => $response->successful() ? 'sent' : 'failed',
                'response_payload' => $response->body(),
                'sent_at'          => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Telegram notification error: " . $e->getMessage());
        }
    }
}
