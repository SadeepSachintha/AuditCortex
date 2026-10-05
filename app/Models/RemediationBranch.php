<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemediationBranch extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }

    public function reviewSession(): BelongsTo
    {
        return $this->belongsTo(ReviewSession::class);
    }
}
