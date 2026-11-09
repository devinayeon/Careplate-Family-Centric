<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'status', 'revision_counter', 'candidate', 'source_provider', 'source_id', 'source_url',
    'snapshot_version', 'source_sha256', 'food_id', 'decision_hash', 'decision_result', 'decided_by',
    'decided_at',
])]
class FoodSubmission extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'revision_counter' => 'integer',
            'candidate' => 'array',
            'decision_result' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Food, $this> */
    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
