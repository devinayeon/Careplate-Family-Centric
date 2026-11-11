<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'aliases', 'basis', 'energy_kcal', 'protein_g', 'fat_g', 'carbohydrate_g', 'sodium_mg',
    'sugars_total_g', 'energy_method', 'source_provider', 'source_id', 'source_url', 'snapshot_version',
    'source_sha256', 'source_description', 'status', 'current_revision', 'search_text',
    'verifier_audit_id', 'verified_at', 'verified_by',
])]
class Food extends Model
{
    use HasUuids;

    // "food" is uncountable for Laravel's pluralizer; the table is `foods`.
    protected $table = 'foods';

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'energy_kcal' => 'decimal:4',
            'protein_g' => 'decimal:4',
            'fat_g' => 'decimal:4',
            'carbohydrate_g' => 'decimal:4',
            'sodium_mg' => 'decimal:4',
            'sugars_total_g' => 'decimal:4',
            'current_revision' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    /** @param Builder<Food> $query */
    public function scopeVerified(Builder $query): void
    {
        $query->where('status', 'verified');
    }

    /** @return HasMany<FoodRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(FoodRevision::class);
    }

    /** @return HasMany<FoodSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(FoodSubmission::class);
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
