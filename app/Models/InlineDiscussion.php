<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InlineDiscussion extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function finding(): BelongsTo
    {
        return $this->belongsTo(VulnerabilityFinding::class, 'vulnerability_finding_id');
    }
}
