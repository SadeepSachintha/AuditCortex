<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function reviewSession(): BelongsTo
    {
        return $this->belongsTo(ReviewSession::class);
    }
}
