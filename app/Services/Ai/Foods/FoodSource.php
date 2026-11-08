<?php

namespace App\Services\Ai\Foods;

use App\Models\Food;
use Carbon\CarbonImmutable;

/**
 * Public wording of a food's source: shared by chat citations and GET /ai/v1/foods so both say the same thing.
 * The level is source review only, never a nutritionist or laboratory claim.
 */
final class FoodSource
{
    public const LEVEL = 'source_reviewed';

    private const PROVIDERS = ['USDA_FDC' => 'USDA FoodData Central'];

    public static function reference(Food $food): string
    {
        $provider = self::PROVIDERS[$food->source_provider] ?? $food->source_provider;
        $text = "{$provider}, ID {$food->source_id}, snapshot {$food->snapshot_version}";
        $description = trim((string) $food->source_description);
        if ($description !== '') {
            $text .= ': '.rtrim($description, '. ');
        }

        return $text.'. Ditinjau sumber (source-reviewed).';
    }

    /** ISO-8601 UTC, e.g. 2026-11-06T02:00:00Z. */
    public static function verifiedAt(Food $food): string
    {
        return CarbonImmutable::parse($food->verified_at ?? $food->updated_at ?? now())->utc()->format('Y-m-d\TH:i:s\Z');
    }

    /** @return array{food_id: string, revision: int, name: string, basis: string, verification_level: string, source_reference: string} */
    public static function summary(Food $food): array
    {
        return [
            'food_id' => (string) $food->getKey(),
            'revision' => (int) $food->current_revision,
            'name' => $food->name,
            'basis' => '100g',
            'verification_level' => self::LEVEL,
            'source_reference' => self::reference($food),
        ];
    }
}
