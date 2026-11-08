<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'food_id', 'revision', 'action', 'status', 'snapshot', 'actor_id', 'actor_audit_id', 'note',
    'decision_hash', 'result',
])]
class FoodRevision extends Model
{
    use HasUuids;

    /** Append-only audit rows: only created_at exists. */
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'snapshot' => 'array',
            'result' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Food, $this> */
    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
