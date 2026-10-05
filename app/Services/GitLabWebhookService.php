<?php

namespace App\Services;

use App\Jobs\AnalyzePullRequestJob;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\ReviewSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GitLabWebhookService
{
    /**
     * Verify GitLab Secret Token.
     */
    public function verifyGitLabToken(?string $tokenHeader, ?string $secret): bool
    {
        if (empty($secret)) {
            return true; // Secret optional in development / simulation mode
        }

        return hash_equals($secret, (string) $tokenHeader);
    }

    /**
     * Handle GitLab Merge Request Hook.
     */
    public function handleMergeRequestEvent(array $payload): ?ReviewSession
    {
        $objectKind = $payload['object_kind'] ?? '';
        if ($objectKind !== 'merge_request') {
            return null;
        }

        $attr = $payload['object_attributes'] ?? [];
        $action = $attr['action'] ?? 'open';

        if (!in_array($action, ['open', 'update', 'reopen'])) {
            return null;
        }

        $project = $payload['project'] ?? [];
        if (empty($project) || empty($attr)) {
            return null;
        }

        $repoFullName = $project['path_with_namespace'] ?? 'gitlab-group/repo';
        $parts = explode('/', $repoFullName);
        $owner = $parts[0] ?? 'gitlab-owner';
        $name = $parts[1] ?? ($project['name'] ?? $repoFullName);

        // 1. Create or update Repository
        $repository = Repository::firstOrCreate(
            ['repo_full_name' => $repoFullName],
            [
                'provider' => 'gitlab',
                'name' => $name,
                'owner' => $owner,
                'default_branch' => $project['default_branch'] ?? 'main',
                'is_active' => true,
            ]
        );

        // 2. Create or update PullRequest (representing Merge Request)
        $iid = $attr['iid'] ?? 1;
        $pr = PullRequest::updateOrCreate(
            [
                'repository_id' => $repository->id,
                'pr_number' => $iid,
            ],
            [
                'title' => $attr['title'] ?? "GitLab MR #{$iid}",
                'description' => $attr['description'] ?? '',
                'head_branch' => $attr['source_branch'] ?? 'feature',
                'base_branch' => $attr['target_branch'] ?? 'main',
                'head_sha' => $attr['last_commit']['id'] ?? ($attr['source']['commit_id'] ?? Str::random(40)),
                'author_username' => $payload['user']['username'] ?? 'developer',
                'author_avatar_url' => $payload['user']['avatar_url'] ?? null,
                'status' => 'open',
                'diff_url' => $attr['url'] ?? null,
            ]
        );

        // 3. Create Review Session
        $session = ReviewSession::create([
            'pull_request_id' => $pr->id,
            'session_uuid' => (string) Str::uuid(),
            'status' => 'queued',
            'started_at' => now(),
        ]);

        // 4. Dispatch Analysis Job
        AnalyzePullRequestJob::dispatch($session->id, [], $pr->raw_diff);

        Log::info("AuditCortex GitLab MR review queued for {$repoFullName} !{$iid} (Session: {$session->session_uuid})");

        return $session;
    }
}
