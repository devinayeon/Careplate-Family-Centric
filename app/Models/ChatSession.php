<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $title
 * @property string $state
 * @property int $generation
 * @property string|null $active_turn_id
 * @property string|null $active_token
 * @property Carbon|null $active_lease_expires_at
 * @property int $next_sequence
 * @property string|null $last_food_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id', 'title', 'state', 'generation', 'active_turn_id', 'active_token',
    'active_lease_expires_at', 'next_sequence', 'last_food_id',
])]
class ChatSession extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'generation' => 'integer',
            'next_sequence' => 'integer',
            'active_lease_expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ChatTurn, $this> */
    public function turns(): HasMany
    {
        return $this->hasMany(ChatTurn::class, 'session_id');
    }
}
