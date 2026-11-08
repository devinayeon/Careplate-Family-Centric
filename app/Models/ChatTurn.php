<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'session_id', 'client_turn_id', 'sequence', 'request_hash', 'status', 'token', 'generation',
    'lease_expires_at', 'message', 'food_id', 'amount_g', 'mode', 'response', 'citations', 'food_refs',
    'error_code',
])]
class ChatTurn extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'generation' => 'integer',
            'amount_g' => 'decimal:2',
            'lease_expires_at' => 'datetime',
            'response' => 'array',
            'citations' => 'array',
            'food_refs' => 'array',
        ];
    }

    /** @return BelongsTo<ChatSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }
}
