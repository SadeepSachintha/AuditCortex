<?php

namespace App\Jobs;

use App\Models\ReviewSession;
use App\Services\GitHubWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishInlineCommentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $sessionId;

    public function __construct(int $sessionId)
    {
        $this->sessionId = $sessionId;
    }

    public function handle(GitHubWebhookService $webhookService): void
    {
        $session = ReviewSession::with('inlineComments')->find($this->sessionId);
        if (!$session) {
            return;
        }

        foreach ($session->inlineComments as $comment) {
            if ($comment->status === 'DRAFT') {
                $webhookService->publishCommentToGitHub($comment);
            }
        }
    }
}
