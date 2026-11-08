<?php

namespace App\Services\Ai\Scan;

use App\Exceptions\Ai\AiException;

/**
 * Strict shape and invariant check of the provider-ocr.schema.json payload.
 * The Gemini parser accepts any JSON array, so nothing here assumes a valid shape.
 * Throws AI_OUTPUT_INVALID and never echoes label content into the exception.
 *
 * @phpstan-type Nutrient array{value: ?string, unit: ?string, canonical_unit: string, qualifier: string, evidence: ?string, akg: ?string}
 * @phpstan-type Serving array{value: ?string, unit: ?string, household_text: ?string}
 * @phpstan-type Label array{readable: bool, basis: string, needs_review: bool, serving: Serving, per_package: ?string, nutrients: array<string, Nutrient>}
 */
final class RawLabelValidator
{
    /** provider key => [public key, canonical unit, allowed raw units] */
    public const NUTRIENTS = [
        'energy' => ['energy_kcal', 'kcal', ['kcal', 'kJ']],
        'fat' => ['fat_g', 'g', ['g']],
        'sugars_total' => ['sugars_total_g', 'g', ['g']],
        'protein' => ['protein_g', 'g', ['g']],
        'carbohydrate' => ['carbohydrate_g', 'g', ['g']],
        'sodium' => ['sodium_mg', 'mg', ['mg', 'g']],
    ];

    private const TOP = ['readable', 'label_basis', 'needs_review', 'serving_size', 'servings_per_package', 'nutrients'];

    private const FIELDS = ['raw_value', 'raw_unit', 'qualifier', 'evidence', 'label_percent_akg'];

    /**
     * Numbers come back as BCMath-safe decimal strings; unknown readings carry no value.
     *
     * @param  array<mixed>  $raw
     * @return Label
     */
    public function validate(array $raw): array
    {
        $raw = $this->object($raw, self::TOP);
        $this->check(is_bool($raw['readable']) && is_bool($raw['needs_review']));
        $this->check(in_array($raw['label_basis'], ['per_serving', 'per_100g', 'per_100ml', 'unknown'], true));

        $serving = $this->object($raw['serving_size'], ['value', 'unit', 'household_text']);
        $this->check(in_array($serving['unit'], ['g', 'ml', null], true));
        $this->check($serving['household_text'] === null || is_string($serving['household_text']));
        $servingValue = $this->positiveOrNull($serving['value']);

        $nutrients = $this->object($raw['nutrients'], array_keys(self::NUTRIENTS));

        $out = [];
        foreach (self::NUTRIENTS as $providerKey => [$key, $canonical, $units]) {
            $out[$key] = $this->nutrient($canonical, $units, $nutrients[$providerKey]);
        }

        return [
            'readable' => $raw['readable'],
            'basis' => $raw['label_basis'],
            'needs_review' => $raw['needs_review'],
            'serving' => [
                'value' => $servingValue,
                'unit' => $servingValue === null ? null : $serving['unit'],
                'household_text' => $serving['household_text'] === null ? null : mb_substr($serving['household_text'], 0, 120),
            ],
            'per_package' => $this->positiveOrNull($raw['servings_per_package']),
            'nutrients' => $out,
        ];
    }

    /**
     * @param  list<string>  $units
     * @return Nutrient
     */
    private function nutrient(string $canonical, array $units, mixed $n): array
    {
        $n = $this->object($n, self::FIELDS);
        $this->check(in_array($n['qualifier'], ['exact', 'less_than', 'unknown'], true));
        $this->check(in_array($n['raw_unit'], ['g', 'mg', 'kcal', 'kJ', null], true));
        $this->check($n['evidence'] === null || is_string($n['evidence']));
        $value = $this->numberOrNull($n['raw_value']);
        $akg = $this->numberOrNull($n['label_percent_akg']);

        if ($n['qualifier'] === 'unknown') {
            return ['value' => null, 'unit' => null, 'canonical_unit' => $canonical,
                'qualifier' => 'unknown', 'evidence' => null, 'akg' => null];
        }

        $evidence = trim((string) $n['evidence']);
        $this->check($value !== null && in_array($n['raw_unit'], $units, true) && $evidence !== '');

        return ['value' => $value, 'unit' => $n['raw_unit'], 'canonical_unit' => $canonical,
            'qualifier' => $n['qualifier'], 'evidence' => mb_substr($evidence, 0, 120), 'akg' => $akg];
    }

    private function numberOrNull(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $this->check((is_int($v) || is_float($v)) && is_finite($v) && $v >= 0);

        return Decimal::fromNumber($v);
    }

    private function positiveOrNull(mixed $v): ?string
    {
        $s = $this->numberOrNull($v);

        return $s === null || Decimal::cmp($s, '0') === 0 ? null : $s;
    }

    /**
     * Value must be a JSON object (string-keyed array) with exactly these keys.
     *
     * @param  list<string>  $keys
     * @return array<mixed>
     */
    private function object(mixed $arr, array $keys): array
    {
        $this->check(is_array($arr));
        $actual = array_keys($arr);
        sort($actual);
        sort($keys);
        $this->check($actual === $keys);

        return $arr;
    }

    private function check(bool $ok): void
    {
        if (! $ok) {
            throw AiException::for('AI_OUTPUT_INVALID');
        }
    }
}
