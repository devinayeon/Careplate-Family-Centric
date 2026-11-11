<?php

namespace App\Services\Ai\Eval;

/**
 * Pure aggregation of repeated scan eval runs (no I/O, no network): the overall summary,
 * one summary per manifest `kind`, error codes, and a per-image view of how stable each field
 * was across the runs. Items are ScanComparer::compare() results plus id, kind, run, latency_ms, usage.
 */
class ScanRunStats
{
    /** 1 counted provider errors as model results; 2 excluded every error but AI_OUTPUT_INVALID; 3 excludes only provider codes and reports harness_errors (see ScanComparer::isProviderError). */
    public const SCORER_VERSION = 3;

    private const PASSING = ['match', 'correct_null'];

    public function __construct(private readonly ScanComparer $comparer = new ScanComparer) {}

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{requests: int, overall: array<string, mixed>, by_kind: array<string, array<string, mixed>>, errors_by_code: array<string, int>, per_image: array<string, array<string, mixed>>, unstable: list<string>}
     */
    public function build(array $items): array
    {
        $byKind = [];
        foreach (array_unique(array_column($items, 'kind')) as $kind) {
            $byKind[$kind] = $this->comparer->summarize(array_values(array_filter($items, fn ($i) => $i['kind'] === $kind)));
        }

        $codes = [];
        foreach ($items as $item) {
            if ($item['outcome'] === 'error') {
                $code = (string) $item['error_code'];
                $codes[$code] = ($codes[$code] ?? 0) + 1;
            }
        }
        ksort($codes);

        [$perImage, $unstable] = $this->perImage($items);

        return [
            'requests' => count($items),
            'overall' => $this->comparer->summarize($items),
            'by_kind' => $byKind,
            'errors_by_code' => $codes,
            'per_image' => $perImage,
            'unstable' => $unstable,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{0: array<string, array<string, mixed>>, 1: list<string>}
     */
    private function perImage(array $items): array
    {
        /** @var array<string, array{kind: string, runs: int, scored_requests: int, provider_errors: int, all_fields_pass_runs: int, fields: array<string, array{pass: int, of: int}>}> $images */
        $images = [];
        foreach ($items as $item) {
            $row = $images[$item['id']] ?? ['kind' => $item['kind'], 'runs' => 0, 'scored_requests' => 0, 'provider_errors' => 0, 'all_fields_pass_runs' => 0, 'fields' => []];
            $row['runs']++;
            if (ScanComparer::isProviderError($item)) {
                $row['provider_errors']++;
                $images[$item['id']] = $row;

                continue;
            }
            $row['scored_requests']++;
            $allPass = true;
            foreach ($item['fields'] as $name => $field) {
                if ($field['result'] === 'not_scored') {
                    continue;
                }
                $pass = in_array($field['result'], self::PASSING, true);
                $counts = $row['fields'][$name] ?? ['pass' => 0, 'of' => 0];
                $row['fields'][$name] = ['pass' => $counts['pass'] + ($pass ? 1 : 0), 'of' => $counts['of'] + 1];
                $allPass = $allPass && $pass;
            }
            $row['all_fields_pass_runs'] += $allPass ? 1 : 0;
            $images[$item['id']] = $row;
        }

        $unstable = [];
        foreach ($images as $id => $row) {
            foreach ($row['fields'] as $name => $f) {
                if ($f['pass'] > 0 && $f['pass'] < $f['of']) {
                    $unstable[] = $id.' '.$name.' '.$f['pass'].'/'.$f['of'];
                }
            }
        }

        return [$images, $unstable];
    }
}
