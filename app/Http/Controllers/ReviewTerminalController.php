<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzePullRequestJob;
use App\Models\InlineComment;
use App\Models\PatchDiff;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\ReviewSession;
use App\Models\VulnerabilityFinding;
use App\Services\AgentEngineClient;
use App\Services\GitHubWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ReviewTerminalController extends Controller
{
    public function index()
    {
        $repositories = Repository::withCount('pullRequests')->get();
        $recentPullRequests = PullRequest::with(['repository', 'latestReviewSession'])
            ->latest()
            ->take(20)
            ->get();

        $stats = [
            'total_repositories' => Repository::count(),
            'total_prs' => PullRequest::count(),
            'total_vulnerabilities' => VulnerabilityFinding::count(),
            'critical_vulnerabilities' => VulnerabilityFinding::where('severity', 'CRITICAL')->count(),
            'high_vulnerabilities' => VulnerabilityFinding::where('severity', 'HIGH')->count(),
            'patches_generated' => PatchDiff::count(),
            'avg_security_score' => round(ReviewSession::where('status', 'completed')->avg('security_score') ?? 100),
        ];

        return view('review_terminal.index', compact('repositories', 'recentPullRequests', 'stats'));
    }

    public function show(int $id)
    {
        $pullRequest = PullRequest::with([
            'repository',
            'reviewSessions.findings',
            'reviewSessions.patches',
            'reviewSessions.logs',
            'reviewSessions.inlineComments',
        ])->findOrFail($id);

        $activeSession = $pullRequest->latestReviewSession;

        return view('review_terminal.show', compact('pullRequest', 'activeSession'));
    }

    public function apiData(int $id): JsonResponse
    {
        $pullRequest = PullRequest::with([
            'repository',
            'latestReviewSession.findings.patch',
            'latestReviewSession.patches',
            'latestReviewSession.logs',
            'latestReviewSession.inlineComments',
        ])->findOrFail($id);

        $session = $pullRequest->latestReviewSession;

        return response()->json([
            'pull_request' => $pullRequest,
            'session' => $session,
            'findings' => $session?->findings ?? [],
            'patches' => $session?->patches ?? [],
            'agent_logs' => $session?->logs ?? [],
            'inline_comments' => $session?->inlineComments ?? [],
        ]);
    }

    public function triggerAudit(int $id, AgentEngineClient $engineClient): JsonResponse
    {
        $pullRequest = PullRequest::with('repository')->findOrFail($id);

        // Create new queued session
        $session = ReviewSession::create([
            'pull_request_id' => $pullRequest->id,
            'session_uuid' => (string) Str::uuid(),
            'status' => 'queued',
            'started_at' => now(),
        ]);

        // Dispatch immediately
        AnalyzePullRequestJob::dispatchSync($session->id, [], $pullRequest->raw_diff);

        return response()->json([
            'message' => 'Autonomous security audit completed successfully.',
            'session_id' => $session->id,
            'session_uuid' => $session->session_uuid,
            'status' => $session->fresh()->status,
            'security_score' => $session->fresh()->security_score,
            'total_findings' => $session->fresh()->total_findings,
        ]);
    }

    public function publishComment(int $commentId, GitHubWebhookService $service): JsonResponse
    {
        $comment = InlineComment::with('reviewSession.pullRequest.repository')->findOrFail($commentId);
        $result = $service->publishCommentToGitHub($comment);

        return response()->json([
            'message' => 'Inline comment published to GitHub pull request line.',
            'result' => $result,
            'comment' => $comment->fresh(),
        ]);
    }

    public function publishAllComments(int $sessionId, GitHubWebhookService $service): JsonResponse
    {
        $session = ReviewSession::with(['inlineComments', 'pullRequest.repository'])->findOrFail($sessionId);
        $publishedCount = 0;

        foreach ($session->inlineComments as $comment) {
            if ($comment->status === 'DRAFT') {
                $service->publishCommentToGitHub($comment);
                $publishedCount++;
            }
        }

        return response()->json([
            'message' => "Published {$publishedCount} inline security review comments to GitHub.",
            'published_count' => $publishedCount,
        ]);
    }

    public function applyPatch(int $patchId): JsonResponse
    {
        $patch = PatchDiff::findOrFail($patchId);
        $patch->update(['status' => 'APPLIED']);

        return response()->json([
            'message' => "Unified patch applied cleanly for {$patch->file_path}.",
            'patch' => $patch,
        ]);
    }

    public function downloadPatch(int $sessionId): Response
    {
        $session = ReviewSession::with(['patches', 'pullRequest'])->findOrFail($sessionId);
        $diffContent = "# AuditCortex Autonomous Security Remediation Patch\n";
        $diffContent .= "# Pull Request: #{$session->pullRequest->pr_number} - {$session->pullRequest->title}\n";
        $diffContent .= "# Generated at: " . now()->toIso8601String() . "\n\n";

        foreach ($session->patches as $patch) {
            $diffContent .= $patch->unified_diff . "\n\n";
        }

        $filename = "auditcortex_pr{$session->pullRequest->pr_number}_patch.diff";

        return response($diffContent, 200, [
            'Content-Type' => 'text/x-diff',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
