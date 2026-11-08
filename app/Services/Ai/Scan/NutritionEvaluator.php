<?php

namespace App\Services\Ai\Scan;

use App\Exceptions\Ai\AiException;

/**
 * Deterministic educational comparison against the daily reference in config/nutrition.php.
 * Contributions are BCMath on the normalized per-serving values. Flags are published only when the
 * policy status is "approved"; otherwise flags stay empty and the verdict is policy_pending_validation.
 * Never emits a "balanced"/"healthy"/"safe" conclusion.
 */
class NutritionEvaluator
{
    /** public contribution key => [normalized key, reference key, flag, Indonesian name] */
    private const COMPARED = [
        'sugars_total' => ['sugars_total_g', 'sugars_total_g', 'high_sugar', 'gula'],
        'sodium' => ['sodium_mg', 'sodium_mg', 'high_sodium', 'natrium'],
        'fat_total' => ['fat_g', 'fat_g', 'high_fat', 'lemak'],
    ];

    /**
     * @param  array<string, mixed>  $normalized  the LabelNormalizer::normalize() result (extraction_status, extraction.needs_review, values)
     * @return array{assessment: array<string, mixed>, warnings: list<string>}
     *
     * @throws AiException POLICY_CONFIG_INVALID for bad config, INTERNAL for a malformed input
     */
    public function evaluate(array $normalized): array
    {
        $policy = $this->policy();
        $reference = $this->reference();
        $threshold = $this->threshold();
        $values = $normalized['values'] ?? null;
        $extraction = $normalized['extraction'] ?? null;
        if (! is_array($values) || ! is_array($extraction) || ! is_string($normalized['extraction_status'] ?? null)) {
            throw AiException::for('INTERNAL');
        }

        $needsReview = (bool) ($extraction['needs_review'] ?? true);
        $exact = [];      // key => BCMath percent at full precision, or null
        $contribution = [];
        foreach (self::COMPARED as $key => [$valueKey]) {
            $value = $needsReview ? null : ($values[$valueKey] ?? null);
            $exact[$key] = $value === null ? null : Decimal::div(Decimal::mul($value, '100'), $reference[$key]);
            $contribution[$key] = $exact[$key] === null ? null : Decimal::toNumber($exact[$key], 2);
        }

        $approved = $policy['status'] === 'approved';
        $flags = $approved && ! $needsReview ? $this->flags($values, $reference, $threshold) : [];
        $missing = array_keys(array_filter($exact, fn ($v) => $v === null));
        $complete = $normalized['extraction_status'] === 'complete' && ! $needsReview && $missing === [];

        $verdict = match (true) {
            $flags !== [] => 'high_flags',
            $approved && $complete => 'no_high_flag',
            ! $approved && count($missing) < count(self::COMPARED) && ! $needsReview => 'policy_pending_validation',
            default => 'insufficient_data',
        };

        return [
            'assessment' => [
                'policy' => $policy,
                'daily_reference_contribution_percent' => $contribution,
                'flags' => $flags,
                'verdict' => $verdict,
                'summary' => $this->summary($verdict, $needsReview, $exact, $flags, $missing, $threshold),
            ],
            'warnings' => $this->warnings($approved, $threshold),
        ];
    }

    /**
     * Flag when value/reference*100 >= threshold, compared without division (exact).
     *
     * @param  array<array-key, mixed>  $values
     * @param  array<string, string>  $reference
     * @return list<string>
     */
    private function flags(array $values, array $reference, string $threshold): array
    {
        $flags = [];
        foreach (self::COMPARED as $key => [$valueKey, , $flag]) {
            $value = $values[$valueKey] ?? null;
            if ($value !== null && Decimal::cmp(Decimal::mul($value, '100'), Decimal::mul($reference[$key], $threshold)) >= 0) {
                $flags[] = $flag;
            }
        }

        return $flags;
    }

    /**
     * @param  array<string, string|null>  $exact
     * @param  list<string>  $flags
     * @param  list<string>  $missing
     */
    private function summary(string $verdict, bool $needsReview, array $exact, array $flags, array $missing, string $threshold): string
    {
        $retake = 'Ambil foto tabel yang lebih jelas.';
        if ($needsReview) {
            return "Kolom label bertentangan atau tidak pasti, sehingga kontribusi tidak dihitung. {$retake}";
        }

        $missingText = $missing === []
            ? 'Data label belum lengkap.'
            : ucfirst($this->join(array_map(fn ($k) => self::COMPARED[$k][3], $missing))).' belum terbaca.';
        if ($verdict === 'insufficient_data') {
            return "{$missingText} {$retake}";
        }

        $text = $this->contributionText($exact);
        if ($flags !== []) {
            $names = array_map(fn ($f) => $this->nameOfFlag($f), $flags);
            $text .= ' Mencapai ambang internal '.Decimal::display($threshold).'%: '.implode(', ', $names).'.';
        } elseif ($verdict === 'no_high_flag') {
            $text .= ' Tidak ada yang mencapai ambang internal '.Decimal::display($threshold).'%.';
        } else {
            $text .= ' Klasifikasi menunggu validasi kebijakan.';
        }

        return $missing === [] ? $text : "{$text} {$missingText} {$retake}";
    }

    /**
     * "Kontribusi gula 24%, natrium 22,5%, dan lemak 11,94% dari pembanding edukasi per sajian."
     *
     * @param  array<string, string|null>  $exact
     */
    private function contributionText(array $exact): string
    {
        $parts = [];
        foreach (self::COMPARED as $key => [, , , $name]) {
            if ($exact[$key] !== null) {
                $parts[] = $name.' '.Decimal::display($exact[$key]).'%';
            }
        }

        return $parts === [] ? '' : 'Kontribusi '.$this->join($parts).' dari pembanding edukasi per sajian.';
    }

    private function nameOfFlag(string $flag): string
    {
        foreach (self::COMPARED as [, , $f, $name]) {
            if ($f === $flag) {
                return $name;
            }
        }

        return $flag;
    }

    /** @param list<string> $items */
    private function join(array $items): string
    {
        if (count($items) <= 2) {
            return implode(' dan ', $items);
        }
        $last = array_pop($items);

        return implode(', ', $items).', dan '.$last;
    }

    /** @return list<string> */
    private function warnings(bool $approved, string $threshold): array
    {
        return [
            'Pembanding edukasi dewasa umum; bukan %AKG label atau kebutuhan personal.',
            $approved
                ? 'Ambang '.Decimal::display($threshold).'% adalah usulan internal, bukan klasifikasi resmi BPOM.'
                : 'Kebijakan skrining internal belum disetujui; hasil tidak membuktikan keseimbangan pola makan.',
        ];
    }

    /** @return array{id: string, status: string, scope: string} */
    private function policy(): array
    {
        $p = config('nutrition.policy');
        if (! is_array($p) || ! is_string($p['id'] ?? null) || $p['id'] === ''
            || ! in_array($p['status'] ?? null, ['provisional', 'approved', 'disabled'], true)
            || ($p['scope'] ?? null) !== 'general_adult_educational_screening') {
            throw AiException::for('POLICY_CONFIG_INVALID');
        }

        return ['id' => $p['id'], 'status' => $p['status'], 'scope' => $p['scope']];
    }

    /** @return array<string, string> contribution key => positive decimal string */
    private function reference(): array
    {
        $ref = [];
        foreach (self::COMPARED as $key => [, $refKey]) {
            $ref[$key] = $this->positiveDecimal(config("nutrition.daily_reference.{$refKey}"), false);
        }

        return $ref;
    }

    private function threshold(): string
    {
        if (config('nutrition.screening.comparison') !== 'gte') {
            throw AiException::for('POLICY_CONFIG_INVALID');
        }

        return $this->positiveDecimal(config('nutrition.screening.threshold_percent'), false);
    }

    private function positiveDecimal(mixed $v, bool $allowZero): string
    {
        $ok = (is_string($v) || is_int($v)) && preg_match('/^\d+(\.\d+)?$/', (string) $v);
        if (! $ok || (! $allowZero && Decimal::cmp((string) $v, '0') <= 0)) {
            throw AiException::for('POLICY_CONFIG_INVALID');
        }

        return (string) $v;
    }
}
