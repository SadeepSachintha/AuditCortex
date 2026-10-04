<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewSession extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'llm_runner_info' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(VulnerabilityFinding::class);
    }

    public function patches(): HasMany
    {
        return $this->hasMany(PatchDiff::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AgentLog::class)->orderBy('step_index', 'asc');
    }

    public function inlineComments(): HasMany
    {
        return $this->hasMany(InlineComment::class);
    }
}
