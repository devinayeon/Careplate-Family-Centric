<?php

namespace App\Services\Ai\Scan;

use App\Exceptions\Ai\AiException;

/**
 * Turns the raw provider reading into the public `extraction`, `normalized_per_serving`
 * and `warnings` parts of scan.schema.json. All math is BCMath on strings; the model
 * only reads, it never supplies a product number. Unknown stays null, never 0.
 *
 * @phpstan-import-type Label from RawLabelValidator
 * @phpstan-import-type Nutrient from RawLabelValidator
 * @phpstan-import-type Serving from RawLabelValidator
 *
 * @phpstan-type Computed array{value: ?string, unit: ?string, canonical_unit: string, qualifier: string, evidence: ?string, akg: ?string, label_value: ?string}
 */
class LabelNormalizer
{
    private const KJ_PER_KCAL = '4.184';

    /** Matches salt wording (Indonesian, English, and the Dutch/French/Spanish/German words seen on imported packs) that is not accompanied by sodium wording. */
    private const SALT = '/\b(garam|salt|zout|sel|sal|salz)\b/iu';

    /** Sodium chloride is salt even when it names sodium ("natrium klorida"): its grams are not sodium grams. */
    private const CHLORIDE = '/\b(klorida|chloride|nacl)\b/iu';

    private const SODIUM = '/\b(natrium|sodium|sodio|sódio|na)\b/iu';

    public function __construct(private readonly RawLabelValidator $validator = new RawLabelValidator) {}

    /**
     * @param  array<string, mixed>  $raw  provider-ocr.schema.json payload (any array is accepted and checked)
     * @return array{
     *     extraction_status: 'complete'|'partial',
     *     extraction: array<string, mixed>,
     *     normalized_per_serving: array<string, int|float|null>,
     *     values: array<string, string|null>,
     *     warnings: list<string>
     * }
     *
     * @throws AiException AI_OUTPUT_INVALID for a non-conforming payload, OCR_UNREADABLE when nothing was read
     */
    public function normalize(array $raw): array
    {
        $label = $this->validator->validate($raw);
        $warnings = [];

        if (! $label['readable']) {
            throw AiException::for('OCR_UNREADABLE');
        }

        $this->dropSaltAsSodium($label, $warnings);

        $read = array_filter($label['nutrients'], fn ($n) => $n['value'] !== null);
        if ($read === []) {
            throw AiException::for('OCR_UNREADABLE');
        }

        $nutrients = [];
        foreach ($label['nutrients'] as $key => $n) {
            $nutrients[$key] = $n + ['label_value' => $this->toLabelUnit($n)];
        }

        $values = $this->perServing($label, $nutrients, $warnings);
        $complete = $this->isComplete($label, $nutrients, $values);

        if (! $complete && ! $label['needs_review'] && $label['serving']['value'] === null) {
            $warnings[] = 'Takaran saji tidak terbaca; hasil belum lengkap.';
        }

        return [
            'extraction_status' => $complete ? 'complete' : 'partial',
            'extraction' => $this->extraction($label, $nutrients),
            'normalized_per_serving' => array_map(fn ($v) => $v === null ? null : Decimal::toNumber($v), $values),
            'values' => $values,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * Salt is not sodium: a reading whose evidence names only salt becomes unknown.
     *
     * @param  Label  $label
     * @param  list<string>  $warnings
     */
    private function dropSaltAsSodium(array &$label, array &$warnings): void
    {
        $sodium = $label['nutrients']['sodium_mg'];
        $evidence = (string) $sodium['evidence'];
        $isSalt = preg_match(self::CHLORIDE, $evidence) || (preg_match(self::SALT, $evidence) && ! preg_match(self::SODIUM, $evidence));
        if ($sodium['value'] !== null && $isSalt) {
            $label['nutrients']['sodium_mg'] = ['value' => null, 'unit' => null, 'canonical_unit' => 'mg',
                'qualifier' => 'unknown', 'evidence' => null, 'akg' => null];
            $warnings[] = 'Kadar garam tidak sama dengan natrium; natrium dianggap belum terbaca.';
        }
    }

    /**
     * Value in the canonical unit (kcal, g, mg) on the label's own basis, or null.
     *
     * @param  Nutrient  $n
     */
    private function toLabelUnit(array $n): ?string
    {
        if ($n['value'] === null) {
            return null;
        }

        return match (true) {
            $n['unit'] === 'kJ' => Decimal::div($n['value'], self::KJ_PER_KCAL),
            $n['canonical_unit'] === 'mg' && $n['unit'] === 'g' => Decimal::mul($n['value'], '1000'),
            default => $n['value'],
        };
    }

    /**
     * Exact per-serving values as trimmed decimal strings; null whenever they cannot be derived.
     *
     * @param  Label  $label
     * @param  array<string, Computed>  $nutrients
     * @param  list<string>  $warnings
     * @return array<string, string|null>
     */
    private function perServing(array $label, array $nutrients, array &$warnings): array
    {
        $factor = $this->factor($label, $warnings);   // null means "cannot derive"
        $out = [];
        foreach ($nutrients as $key => $n) {
            $exact = $n['qualifier'] === 'exact' && $n['label_value'] !== null;
            $out[$key] = $exact && $factor !== null
                ? Decimal::round(Decimal::mul($n['label_value'], $factor), 6)
                : null;
        }

        return $out;
    }

    /**
     * Multiplier from the label basis to one serving, or null when it is not derivable.
     *
     * @param  Label  $label
     * @param  list<string>  $warnings
     */
    private function factor(array $label, array &$warnings): ?string
    {
        if ($label['needs_review']) {
            $warnings[] = 'Kolom label bertentangan atau tidak pasti; periksa label dan ulangi foto.';

            return null;
        }

        $serving = $label['serving'];

        return match ($label['basis']) {
            'per_serving' => '1',
            'per_100g', 'per_100ml' => $this->perHundred($label['basis'], $serving, $warnings),
            default => $this->warn($warnings, 'Basis label tidak jelas; nilai per sajian tidak dihitung.'),
        };
    }

    /**
     * @param  Serving  $serving
     * @param  list<string>  $warnings
     */
    private function perHundred(string $basis, array $serving, array &$warnings): ?string
    {
        $unit = $basis === 'per_100g' ? 'g' : 'ml';
        if ($serving['value'] === null || $serving['unit'] !== $unit) {
            return $this->warn($warnings, $serving['value'] !== null
                ? "Label per 100 {$unit} tetapi takaran saji bukan dalam {$unit}; tidak dikonversi."
                : 'Takaran saji tidak tersedia; nilai per sajian tidak dihitung.');
        }

        return Decimal::div($serving['value'], '100');
    }

    /** @param list<string> $warnings */
    private function warn(array &$warnings, string $message): null
    {
        $warnings[] = $message;

        return null;
    }

    /**
     * Complete needs: no conflict, known basis, serving size, and six exact, derived values.
     *
     * @param  Label  $label
     * @param  array<string, Computed>  $nutrients
     * @param  array<string, string|null>  $values
     */
    private function isComplete(array $label, array $nutrients, array $values): bool
    {
        return ! $label['needs_review']
            && $label['basis'] !== 'unknown'
            && $label['serving']['value'] !== null
            && $label['serving']['unit'] !== null
            && count(array_filter($values, fn ($v) => $v !== null)) === count($nutrients);
    }

    /**
     * @param  Label  $label
     * @param  array<string, Computed>  $nutrients
     * @return array<string, mixed>
     */
    private function extraction(array $label, array $nutrients): array
    {
        $public = [];
        foreach ($nutrients as $key => $n) {
            $known = $n['label_value'] !== null;
            $public[$key] = [
                'value' => $known ? Decimal::toNumber($n['label_value']) : null,
                'qualifier' => $n['qualifier'],
                'unit' => $n['canonical_unit'],
                'evidence' => $n['evidence'],
                'label_percent_akg' => $n['akg'] === null ? null : Decimal::toNumber($n['akg']),
            ];
        }

        return [
            'label_basis' => $label['basis'],
            'serving_size' => [
                'value' => $label['serving']['value'] === null ? null : Decimal::toNumber($label['serving']['value']),
                'unit' => $label['serving']['unit'],
                'household_text' => $label['serving']['household_text'],
            ],
            'servings_per_package' => $label['per_package'] === null ? null : Decimal::toNumber($label['per_package']),
            'nutrients' => $public,
            'needs_review' => $label['needs_review'],
        ];
    }
}
