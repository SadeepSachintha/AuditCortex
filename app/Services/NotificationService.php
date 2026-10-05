<?php

namespace App\Services;

use App\Models\PullRequest;
use App\Models\ReviewSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send Slack alert for high-risk / merge-blocked PRs.
     */
    public function sendSlackAlert(PullRequest $pr, ReviewSession $session, ?string $webhookUrl = null): array
    {
        $url = $webhookUrl ?: ($pr->repository->settings['slack_webhook_url'] ?? config('services.slack.webhook_url'));
        
        $isBlocked = ($session->security_score < 80) || ($session->critical_count > 0);
        $statusEmoji = $isBlocked ? '⛔' : '✅';
        $statusText = $isBlocked ? 'MERGE BLOCKED' : 'MERGE ALLOWED';
        $color = $isBlocked ? '#f43f5e' : '#10b981';

        $payload = [
            'username' => 'AuditCortex Security Bot',
            'icon_emoji' => ':shield:',
            'attachments' => [
                [
                    'color' => $color,
                    'title' => "{$statusEmoji} AuditCortex Gate: {$statusText} on PR #{$pr->pr_number}",
                    'title_link' => url("/reviews/{$pr->id}"),
                    'text' => "*Repository:* `{$pr->repository->repo_full_name}`\n*Pull Request:* {$pr->title}\n*Author:* {$pr->author_username}\n*Security Health Score:* *{$session->security_score}/100*\n*Vulnerabilities:* {$session->critical_count} Critical, {$session->high_count} High, {$session->medium_count} Medium",
                    'fields' => [
                        [
                            'title' => 'Merge Protection Gate',
                            'value' => $isBlocked ? 'Blocked (Violates Enterprise Policy)' : 'Passed (Eligible to Merge)',
                            'short' => true,
                        ],
                        [
                            'title' => 'AST Unified Patches',
                            'value' => "{$session->patches()->count()} Synthesized Clean",
                            'short' => true,
                        ]
                    ],
                    'actions' => [
                        [
                            'type' => 'button',
                            'text' => 'Review in AuditCortex 🛡️',
                            'url' => url("/reviews/{$pr->id}"),
                            'style' => 'primary',
                        ],
                        [
                            'type' => 'button',
                            'text' => 'Download SARIF 2.1.0 📥',
                            'url' => url("/reviews/{$session->id}/download-sarif"),
                        ]
                    ],
                    'footer' => 'AuditCortex Autonomous Security Agent Loop',
                    'ts' => time(),
                ]
            ]
        ];

        if ($url) {
            try {
                $response = Http::timeout(5)->post($url, $payload);
                return [
                    'status' => 'sent',
                    'http_code' => $response->status(),
                    'payload' => $payload,
                ];
            } catch (\Exception $e) {
                Log::warning("Slack webhook delivery error: " . $e->getMessage());
                return [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'payload' => $payload,
                ];
            }
        }

        // Simulated local delivery
        return [
            'status' => 'simulated_local',
            'message' => 'Slack notification generated and formatted cleanly (No webhook URL configured).',
            'payload' => $payload,
        ];
    }

    /**
     * Send Microsoft Teams Adaptive Card alert.
     */
    public function sendTeamsAlert(PullRequest $pr, ReviewSession $session, ?string $webhookUrl = null): array
    {
        $url = $webhookUrl ?: ($pr->repository->settings['teams_webhook_url'] ?? null);
        $isBlocked = ($session->security_score < 80) || ($session->critical_count > 0);

        $payload = [
            '@type' => 'MessageCard',
            '@context' => 'http://schema.org/extensions',
            'themeColor' => $isBlocked ? 'F43F5E' : '10B981',
            'summary' => "AuditCortex Security Alert: PR #{$pr->pr_number}",
            'sections' => [
                [
                    'activityTitle' => "🛡️ AuditCortex Security Gate: " . ($isBlocked ? "MERGE BLOCKED" : "MERGE ALLOWED"),
                    'activitySubtitle' => "PR #{$pr->pr_number} in {$pr->repository->repo_full_name}",
                    'facts' => [
                        ['name' => 'Security Health Score', 'value' => "{$session->security_score}/100"],
                        ['name' => 'Critical Flaws', 'value' => (string) $session->critical_count],
                        ['name' => 'High Flaws', 'value' => (string) $session->high_count],
                        ['name' => 'Author', 'value' => $pr->author_username ?? 'developer'],
                    ],
                    'markdown' => true,
                ]
            ],
            'potentialAction' => [
                [
                    '@type' => 'OpenUri',
                    'name' => 'Inspect PR & AI Critic Thread',
                    'targets' => [
                        ['os' => 'default', 'uri' => url("/reviews/{$pr->id}")],
                    ]
                ]
            ]
        ];

        if ($url) {
            try {
                $response = Http::timeout(5)->post($url, $payload);
                return [
                    'status' => 'sent',
                    'http_code' => $response->status(),
                    'payload' => $payload,
                ];
            } catch (\Exception $e) {
                Log::warning("Teams webhook error: " . $e->getMessage());
                return [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'payload' => $payload,
                ];
            }
        }

        return [
            'status' => 'simulated_local',
            'message' => 'Microsoft Teams Card prepared cleanly.',
            'payload' => $payload,
        ];
    }
}
