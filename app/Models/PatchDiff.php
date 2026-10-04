<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatchDiff extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_ast_verified' => 'boolean',
    ];

    public function reviewSession(): BelongsTo
    {
        return $this->belongsTo(ReviewSession::class);
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(VulnerabilityFinding::class, 'vulnerability_finding_id');
    }
}
