<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $session_id
 * @property string $client_turn_id
 * @property int $sequence
 * @property string $request_hash
 * @property string $status
 * @property string $token
 * @property int $generation
 * @property Carbon|null $lease_expires_at
 * @property string|null $message
 * @property string|null $food_id
 * @property string|null $amount_g
 * @property string|null $mode
 * @property array<string, mixed>|null $response
 * @property list<array<string, mixed>>|null $citations
 * @property list<array<string, mixed>>|null $food_refs
 * @property string|null $error_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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
