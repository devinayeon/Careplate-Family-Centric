<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
