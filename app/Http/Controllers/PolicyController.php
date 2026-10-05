<?php

namespace App\Http\Controllers;

use App\Models\PullRequest;
use App\Models\Repository;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PolicyController extends Controller
{
    public function getPolicy(int $repoId): JsonResponse
    {
        $repo = Repository::findOrFail($repoId);
        $policy = $repo->settings['security_policy'] ?? [
            'min_security_score' => 80,
            'fail_on' => ['CRITICAL', 'HIGH'],
            'max_critical_allowed' => 0,
            'ignore_paths' => ['tests/**', 'spec/**', 'fixtures/**', 'mocks/**'],
            'enforce_sarif_export' => true,
            'slack_webhook_url' => '',
            'custom_rules' => [
                [
                    'id' => 'CORP-POL-001',
                    'name' => 'Strict Parameterized Queries Only',
                    'severity' => 'CRITICAL',
                    'description' => 'Enterprise policy forbids raw query interpolation without prepared statement binding.'
                ],
                [
                    'id' => 'CORP-POL-002',
                    'name' => 'Disallow Shell Execution in Web Endpoints',
                    'severity' => 'CRITICAL',
                    'description' => 'Direct shell invocation (os.system, exec, shell_exec) is strictly prohibited in web endpoints.'
                ]
            ]
        ];

        return response()->json([
            'repository' => $repo->repo_full_name,
            'policy' => $policy,
        ]);
    }

    public function updatePolicy(Request $request, int $repoId): JsonResponse
    {
        $repo = Repository::findOrFail($repoId);
        $settings = $repo->settings ?? [];

        $policy = [
            'min_security_score' => (int) $request->input('min_security_score', 80),
            'fail_on' => $request->input('fail_on', ['CRITICAL', 'HIGH']),
            'max_critical_allowed' => (int) $request->input('max_critical_allowed', 0),
            'ignore_paths' => $request->input('ignore_paths', ['tests/**', 'spec/**']),
            'enforce_sarif_export' => (bool) $request->input('enforce_sarif_export', true),
            'slack_webhook_url' => $request->input('slack_webhook_url', ''),
            'custom_rules' => $request->input('custom_rules', []),
        ];

        $settings['security_policy'] = $policy;
        $repo->update(['settings' => $settings]);

        return response()->json([
            'message' => 'Organization security policy updated successfully.',
            'policy' => $policy,
        ]);
    }

    public function downloadYaml(int $repoId): Response
    {
        $repo = Repository::findOrFail($repoId);
        $policy = $repo->settings['security_policy'] ?? [
            'min_security_score' => 80,
            'fail_on' => ['CRITICAL', 'HIGH'],
            'max_critical_allowed' => 0,
            'ignore_paths' => ['tests/**', 'spec/**'],
            'enforce_sarif_export' => true,
        ];

        $yaml = "# ==========================================================\n";
        $yaml .= "# AuditCortex Enterprise Security Policy: .auditcortex.yml\n";
        $yaml .= "# Repository: {$repo->repo_full_name}\n";
        $yaml .= "# Generated: " . now()->toIso8601String() . "\n";
        $yaml .= "# ==========================================================\n\n";

        $yaml .= "version: \"1.0\"\n";
        $yaml .= "gate:\n";
        $yaml .= "  min_security_score: " . ($policy['min_security_score'] ?? 80) . "\n";
        $yaml .= "  max_critical_allowed: " . ($policy['max_critical_allowed'] ?? 0) . "\n";
        $yaml .= "  fail_on:\n";
        foreach (($policy['fail_on'] ?? ['CRITICAL', 'HIGH']) as $sev) {
            $yaml .= "    - {$sev}\n";
        }
        $yaml .= "  enforce_sarif_export: " . (($policy['enforce_sarif_export'] ?? true) ? "true" : "false") . "\n\n";

        $yaml .= "ignore_paths:\n";
        foreach (($policy['ignore_paths'] ?? ['tests/**', 'spec/**']) as $p) {
            $yaml .= "  - \"{$p}\"\n";
        }

        $yaml .= "\ncustom_rules:\n";
        foreach (($policy['custom_rules'] ?? []) as $rule) {
            $yaml .= "  - id: \"{$rule['id']}\"\n";
            $yaml .= "    name: \"{$rule['name']}\"\n";
            $yaml .= "    severity: \"{$rule['severity']}\"\n";
            $yaml .= "    description: \"{$rule['description']}\"\n";
        }

        return response($yaml, 200, [
            'Content-Type' => 'text/yaml',
            'Content-Disposition' => 'attachment; filename=".auditcortex.yml"',
        ]);
    }

    public function testNotification(Request $request, int $prId, NotificationService $notifier): JsonResponse
    {
        $pr = PullRequest::with(['repository', 'latestReviewSession.patches'])->findOrFail($prId);
        $session = $pr->latestReviewSession;

        if (!$session) {
            return response()->json(['error' => 'No active review session found to generate notification.'], 422);
        }

        $webhookUrl = $request->input('webhook_url');
        $channel = $request->input('channel', 'slack'); // slack, teams

        if ($channel === 'teams') {
            $res = $notifier->sendTeamsAlert($pr, $session, $webhookUrl);
        } else {
            $res = $notifier->sendSlackAlert($pr, $session, $webhookUrl);
        }

        return response()->json([
            'message' => "Security alert notification dispatched ({$channel})!",
            'result' => $res,
        ]);
    }
}
