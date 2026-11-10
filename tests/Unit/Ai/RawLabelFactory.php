<?php

use App\Exceptions\Ai\AiException;
use App\Services\Ai\Scan\LabelNormalizer;

/**
 * Builders for provider-ocr.schema.json shaped input (pure PHP, no framework needed).
 */
function rawNutrient(int|float|null $value, ?string $unit, string $qualifier = 'exact', ?string $evidence = '__auto__', int|float|null $akg = null): array
{
    return [
        'raw_value' => $value,
        'raw_unit' => $unit,
        'qualifier' => $qualifier,
        'evidence' => $evidence === '__auto__' ? ($value === null ? null : 'label '.(is_finite($value) ? $value : 'x')." {$unit}") : $evidence,
        'label_percent_akg' => $akg,
    ];
}

function rawUnknown(): array
{
    return rawNutrient(null, null, 'unknown', null);
}

/**
 * Complete per_serving label, 30 g serving (the provisional example).
 *
 * @param  array<string, mixed>  $nutrients  overrides by provider key
 * @param  array<string, mixed>  $top  overrides of top-level keys
 */
function rawLabel(array $nutrients = [], array $top = []): array
{
    return array_replace([
        'readable' => true,
        'label_basis' => 'per_serving',
        'needs_review' => false,
        'serving_size' => ['value' => 30, 'unit' => 'g', 'household_text' => '3 keping'],
        'servings_per_package' => 2,
        'nutrients' => array_replace([
            'energy' => rawNutrient(150, 'kcal'),
            'fat' => rawNutrient(8, 'g'),
            'sugars_total' => rawNutrient(12, 'g'),
            'protein' => rawNutrient(3, 'g'),
            'carbohydrate' => rawNutrient(20, 'g'),
            'sodium' => rawNutrient(0.45, 'g'),
        ], $nutrients),
    ], $top);
}

function normalizeCode(array $raw): string
{
    try {
        (new LabelNormalizer)->normalize($raw);
    } catch (AiException $e) {
        return $e->errorCode;
    }

    return 'NO_EXCEPTION';
}
