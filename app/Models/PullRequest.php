<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PullRequest extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function reviewSessions(): HasMany
    {
        return $this->hasMany(ReviewSession::class);
    }

    public function latestReviewSession(): HasOne
    {
        return $this->hasOne(ReviewSession::class)->latestOfMany();
    }

    public function checkRuns(): HasMany
    {
        return $this->hasMany(CheckRun::class);
    }

    public function latestCheckRun(): HasOne
    {
        return $this->hasOne(CheckRun::class)->latestOfMany();
    }

    public function remediationBranches(): HasMany
    {
        return $this->hasMany(RemediationBranch::class);
    }
}
