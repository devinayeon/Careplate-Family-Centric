<?php

namespace App\Services\Ai\Eval;

/**
 * Pure comparison of one scan outcome against a manifest truth row, plus the n/N summary.
 * No I/O. Field results: match, mismatch, missing (truth has a value, extraction is null or
 * abstained), correct_null (truth null, extraction null), unsafe (truth null, extraction has
 * a value), not_scored (truth has no entry).
 *
 * @phpstan-type Truth array{expected_outcome: string, label_basis: ?string, serving_size: array{value: ?float, unit: ?string}, nutrients: array<string, ?float>, less_than: list<string>}
 */
class ScanComparer
{
    public const NUTRIENTS = ['energy_kcal', 'fat_g', 'sugars_total_g', 'protein_g', 'carbohydrate_g', 'sodium_mg'];

    private const EPSILON = 1e-6;

    /**
     * @param  Truth  $truth
     * @param  array<string, mixed>|null  $response  public ScanResponse, or null when the pipeline threw
     * @param  string|null  $errorCode  AiException code when $response is null
     * @return array<string, mixed>
     */
    public function compare(array $truth, ?array $response, ?string $errorCode): array
    {
        $abstained = $response === null && $errorCode === 'OCR_UNREADABLE';
        $outcome = $response !== null ? 'extracted' : ($abstained ? 'unreadable' : 'error');
        $extraction = is_array($response['extraction'] ?? null) ? $response['extraction'] : null;

        $fields = [];
        foreach (self::NUTRIENTS as $key) {
            $fields[$key] = $this->nutrient($truth, $key, $extraction['nutrients'][$key] ?? null);
        }
        $fields['serving_size'] = $this->serving($truth['serving_size'], $extraction['serving_size'] ?? null);
        $fields['label_basis'] = $this->basis($truth['label_basis'], $extraction['label_basis'] ?? null);

        return [
            'expected_outcome' => $truth['expected_outcome'],
            'outcome' => $outcome,
            'error_code' => $response === null ? $errorCode : null,
            'abstained' => $abstained,
            'invalid_output' => $response === null && $errorCode === 'AI_OUTPUT_INVALID',
            'negative_correct' => $truth['expected_outcome'] === 'unreadable' && $abstained,
            'unsafe_count' => count(array_filter($fields, fn (array $f) => $f['result'] === 'unsafe')),
            'fields' => $fields,
        ];
    }

    /** Codes that mean the provider or its quota failed, not the model or this code. */
    private const PROVIDER_CODES = ['AI_PROVIDER_ERROR', 'AI_TIMEOUT', 'AI_DISABLED'];

    /**
     * A request the provider failed (HTTP error or quota, timeout, disabled) says nothing about the model, so it is
     * excluded from field, abstention, latency and token scoring. Every other error stays scored (as a missing reading):
     * AI_OUTPUT_INVALID is the model's own unusable output; INTERNAL, POLICY_CONFIG_INVALID, INVALID_INPUT and IMAGE_*
     * are harness or pipeline faults that must stay visible, so they are also counted in `harness_errors`.
     *
     * @param  array<string, mixed>  $item  a compare() result
     */
    public static function isProviderError(array $item): bool
    {
        return $item['outcome'] === 'error' && in_array($item['error_code'], self::PROVIDER_CODES, true);
    }

    /** @param  array<string, mixed>  $item  a compare() result */
    private static function isHarnessError(array $item): bool
    {
        return $item['outcome'] === 'error' && ! self::isProviderError($item) && $item['error_code'] !== 'AI_OUTPUT_INVALID';
    }

    /**
     * @param  list<array<string, mixed>>  $all  compare() results, each with optional split, latency_ms, usage; provider errors are counted but not scored; harness errors stay scored and are also counted
     * @return array<string, mixed>
     */
    public function summarize(array $all): array
    {
        $items = array_values(array_filter($all, fn ($i) => ! self::isProviderError($i)));
        $providerCodes = [];
        foreach (array_filter($all, fn ($i) => self::isProviderError($i)) as $failed) {
            $providerCodes[(string) $failed['error_code']] = ($providerCodes[(string) $failed['error_code']] ?? 0) + 1;
        }
        ksort($providerCodes);
        $harnessCodes = [];
        foreach (array_filter($items, fn ($i) => self::isHarnessError($i)) as $failed) {
            $harnessCodes[(string) $failed['error_code']] = ($harnessCodes[(string) $failed['error_code']] ?? 0) + 1;
        }
        ksort($harnessCodes);
        $fieldNames = [...self::NUTRIENTS, 'serving_size', 'label_basis'];
        $fields = [];
        foreach ($fieldNames as $name) {
            $row = ['correct' => 0, 'total' => 0, 'null_correct' => 0, 'null_total' => 0, 'unsafe' => 0];
            foreach ($items as $item) {
                $field = $item['fields'][$name];
                if ($field['result'] === 'not_scored') {
                    continue;
                }
                $field['truth'] === null ? $row['null_total']++ : $row['total']++;
                $row['correct'] += $field['result'] === 'match' ? 1 : 0;
                $row['null_correct'] += $field['result'] === 'correct_null' ? 1 : 0;
                $row['unsafe'] += $field['result'] === 'unsafe' ? 1 : 0;
            }
            $fields[$name] = $row;
        }

        $negatives = array_filter($items, fn ($i) => $i['expected_outcome'] === 'unreadable');
        $latencies = array_values(array_filter(array_column($items, 'latency_ms'), 'is_int'));

        return [
            'images' => count($all),
            'scored_requests' => count($items),
            'provider_errors' => count($all) - count($items),
            'provider_errors_by_code' => $providerCodes,
            'harness_errors' => array_sum($harnessCodes),
            'harness_errors_by_code' => $harnessCodes,
            'fields' => $fields,
            'abstained' => count(array_filter($items, fn ($i) => $i['abstained'])),
            'false_abstentions' => count(array_filter($items, fn ($i) => $i['abstained'] && $i['expected_outcome'] === 'extract')),
            'negative_total' => count($negatives),
            'negative_correct' => count(array_filter($negatives, fn ($i) => $i['negative_correct'])),
            'invalid_output' => count(array_filter($items, fn ($i) => $i['invalid_output'])),
            'errors' => count(array_filter($all, fn ($i) => $i['outcome'] === 'error')),
            'unsafe_inferences' => array_sum(array_column($items, 'unsafe_count')),
            'latency_ms' => $this->latency($latencies),
            'usage' => $this->usage($items),
        ];
    }

    /**
     * @param  Truth  $truth
     * @return array<string, mixed>
     */
    private function nutrient(array $truth, string $key, mixed $got): array
    {
        $expected = $truth['nutrients'][$key] ?? null;
        $value = is_array($got) ? ($got['value'] ?? null) : null;
        $qualifier = is_array($got) ? ($got['qualifier'] ?? null) : null;
        $wanted = in_array($key, $truth['less_than'], true) ? 'less_than' : 'exact';

        $result = match (true) {
            $value === null => $expected === null ? 'correct_null' : 'missing',
            $expected === null => 'unsafe',
            $this->same($expected, $value) && $qualifier === $wanted => 'match',
            default => 'mismatch',
        };

        return ['result' => $result, 'truth' => $expected, 'got' => $value, 'qualifier' => $qualifier];
    }

    /**
     * @param  array{value: ?float, unit: ?string}  $truth
     * @return array<string, mixed>
     */
    private function serving(array $truth, mixed $got): array
    {
        $value = is_array($got) ? ($got['value'] ?? null) : null;
        $unit = is_array($got) ? ($got['unit'] ?? null) : null;

        $result = match (true) {
            $value === null => $truth['value'] === null ? 'correct_null' : 'missing',
            $truth['value'] === null => 'unsafe',
            $this->same($truth['value'], $value) && $unit === $truth['unit'] => 'match',
            default => 'mismatch',
        };

        return ['result' => $result, 'truth' => $truth['value'] === null ? null : [$truth['value'], $truth['unit']],
            'got' => $value === null ? null : [$value, $unit]];
    }

    /**
     * @return array<string, mixed>
     */
    private function basis(?string $truth, mixed $got): array
    {
        $got = is_string($got) ? $got : null;
        $result = match (true) {
            $truth === null => 'not_scored',
            $got === null || $got === 'unknown' => 'missing',
            $got === $truth => 'match',
            default => 'mismatch',
        };

        return ['result' => $result, 'truth' => $truth, 'got' => $got];
    }

    private function same(float|int $expected, mixed $got): bool
    {
        return (is_int($got) || is_float($got)) && abs($expected - $got) < self::EPSILON;
    }

    /**
     * Nearest-rank percentiles over the recorded latencies.
     *
     * @param  list<int>  $values
     * @return array{n: int, p50: ?int, p95: ?int, max: ?int}
     */
    private function latency(array $values): array
    {
        sort($values);
        $rank = fn (float $p): ?int => $values === [] ? null : $values[max(0, (int) ceil($p * count($values)) - 1)];

        return ['n' => count($values), 'p50' => $rank(0.5), 'p95' => $rank(0.95), 'max' => $values === [] ? null : max($values)];
    }

    /**
     * Sums only the token counts the provider reported; unknown is never added as 0.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array<string, int>
     */
    private function usage(array $items): array
    {
        $sum = ['prompt' => 0, 'candidates' => 0, 'thoughts' => 0, 'total' => 0, 'unknown_reports' => 0];
        foreach ($items as $item) {
            $usage = $item['usage'] ?? null;
            $unknown = ! is_array($usage);
            foreach (['prompt', 'candidates', 'thoughts', 'total'] as $k) {
                $v = is_array($usage) ? ($usage[$k] ?? null) : null;
                is_int($v) ? $sum[$k] += $v : $unknown = true;
            }
            $sum['unknown_reports'] += $unknown ? 1 : 0;
        }

        return $sum;
    }
}
