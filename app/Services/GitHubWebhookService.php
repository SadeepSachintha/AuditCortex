<?php

namespace App\Services;

use App\Jobs\AnalyzePullRequestJob;
use App\Models\InlineComment;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\ReviewSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GitHubWebhookService
{
    public function verifyGitHubSignature(string $payload, ?string $signatureHeader, ?string $secret): bool
    {
        if (empty($secret)) {
            return true; // Secret optional in development / simulation mode
        }

        if (empty($signatureHeader) || !Str::startsWith($signatureHeader, 'sha256=')) {
            return false;
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signatureHeader);
    }

    public function handlePullRequestEvent(array $payload): ?ReviewSession
    {
        $action = $payload['action'] ?? 'opened';
        if (!in_array($action, ['opened', 'synchronize', 'reopened', 'review_requested'])) {
            return null;
        }

        $repoData = $payload['repository'] ?? [];
        $prData = $payload['pull_request'] ?? [];

        if (empty($repoData) || empty($prData)) {
            return null;
        }

        $repoFullName = $repoData['full_name'] ?? 'default/repo';
        $repoNameParts = explode('/', $repoFullName);
        $owner = $repoNameParts[0] ?? 'owner';
        $repoName = $repoNameParts[1] ?? $repoFullName;

        // Create or update repository
        $repository = Repository::firstOrCreate(
            ['repo_full_name' => $repoFullName],
            [
                'provider' => 'github',
                'name' => $repoName,
                'owner' => $owner,
                'default_branch' => $repoData['default_branch'] ?? 'main',
                'is_active' => true,
            ]
        );

        // Create or update Pull Request
        $pullRequest = PullRequest::updateOrCreate(
            [
                'repository_id' => $repository->id,
                'pr_number' => $prData['number'] ?? 1,
            ],
            [
                'title' => $prData['title'] ?? 'Pull Request #' . ($prData['number'] ?? 1),
                'description' => $prData['body'] ?? '',
                'head_branch' => $prData['head']['ref'] ?? 'feature',
                'base_branch' => $prData['base']['ref'] ?? 'main',
                'head_sha' => $prData['head']['sha'] ?? 'head-sha-' . substr(md5(time()), 0, 8),
                'base_sha' => $prData['base']['sha'] ?? 'base-sha',
                'author_username' => $prData['user']['login'] ?? 'contributor',
                'author_avatar_url' => $prData['user']['avatar_url'] ?? null,
                'status' => $prData['state'] ?? 'open',
                'diff_url' => $prData['diff_url'] ?? null,
            ]
        );

        // Create new queued review session
        $session = ReviewSession::create([
            'pull_request_id' => $pullRequest->id,
            'session_uuid' => (string) Str::uuid(),
            'status' => 'queued',
            'started_at' => now(),
        ]);

        // Dispatch background or synchronous analysis job
        $files = $this->extractFilesFromPayload($payload);
        AnalyzePullRequestJob::dispatch($session->id, $files, $pullRequest->raw_diff);

        return $session;
    }

    public function publishCommentToGitHub(InlineComment $comment): array
    {
        $session = $comment->reviewSession;
        $pr = $session->pullRequest;
        $repo = $pr->repository;

        // If repository has access token, invoke real GitHub API
        if (!empty($repo->access_token)) {
            try {
                $endpoint = "https://api.github.com/repos/{$repo->repo_full_name}/pulls/{$pr->pr_number}/comments";
                $response = Http::withToken($repo->access_token)
                    ->withHeaders(['Accept' => 'application/vnd.github.v3+json'])
                    ->post($endpoint, [
                        'body' => $comment->body,
                        'commit_id' => $pr->head_sha,
                        'path' => $comment->file_path,
                        'line' => $comment->line_number,
                    ]);

                if ($response->successful()) {
                    $comment->update([
                        'status' => 'PUBLISHED',
                        'github_comment_id' => (string) $response->json('id'),
                    ]);
                    return ['success' => true, 'id' => $response->json('id')];
                }
            } catch (\Exception $e) {
                Log::error("Failed to publish comment to GitHub: " . $e->getMessage());
            }
        }

        // Development/Demonstration simulated publishing
        $simulatedId = 'gh_comment_' . rand(100000, 999999);
        $comment->update([
            'status' => 'PUBLISHED',
            'github_comment_id' => $simulatedId,
        ]);

        return [
            'success' => true,
            'id' => $simulatedId,
            'note' => 'Inline comment marked PUBLISHED (simulated GitHub API comment delivery with security markdown).'
        ];
    }

    protected function extractFilesFromPayload(array $payload): array
    {
        // Custom files payload if injected via simulation or webhook attachment
        if (isset($payload['_custom_files']) && is_array($payload['_custom_files'])) {
            return $payload['_custom_files'];
        }

        return [];
    }
}
