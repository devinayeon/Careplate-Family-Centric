<?php

use App\Services\Ai\Eval\ScanComparer;
use App\Services\Ai\Eval\ScanRunStats;

function statsTruth(array $over = []): array
{
    return array_replace([
        'expected_outcome' => 'extract',
        'label_basis' => 'per_serving',
        'serving_size' => ['value' => 30.0, 'unit' => 'g'],
        'nutrients' => ['energy_kcal' => 150.0, 'fat_g' => 8.0, 'sugars_total_g' => 12.0, 'protein_g' => 3.0, 'carbohydrate_g' => 20.0, 'sodium_mg' => 450.0],
        'less_than' => [],
    ], $over);
}

function statsResponse(array $nutrients = []): array
{
    $values = array_replace(['energy_kcal' => 150, 'fat_g' => 8, 'sugars_total_g' => 12, 'protein_g' => 3, 'carbohydrate_g' => 20, 'sodium_mg' => 450], $nutrients);
    $out = [];
    foreach ($values as $key => $value) {
        $out[$key] = ['value' => $value, 'qualifier' => $value === null ? 'unknown' : 'exact'];
    }

    return ['extraction' => ['label_basis' => 'per_serving', 'serving_size' => ['value' => 30, 'unit' => 'g', 'household_text' => null], 'nutrients' => $out]];
}

/** One scored item: a compare() result plus the run metadata the aggregator needs. */
function runItem(string $id, string $kind, int $run, array $nutrients = [], ?array $response = null, ?string $code = null, array $truth = []): array
{
    $compared = (new ScanComparer)->compare(statsTruth($truth), $code === null ? ($response ?? statsResponse($nutrients)) : null, $code);

    return ['id' => $id, 'kind' => $kind, 'run' => $run, 'latency_ms' => 1000 * $run, 'usage' => null] + $compared;
}

it('counts per-field pass rates across repeated runs of the same image', function () {
    $items = [];
    foreach ([1, 2, 3] as $run) {
        // energy wrong in run 3 only; everything else right in all runs
        $items[] = runItem('A', 'real_id', $run, $run === 3 ? ['energy_kcal' => 15] : []);
    }

    $stats = (new ScanRunStats)->build($items);

    expect($stats['requests'])->toBe(3)
        ->and($stats['overall']['fields']['energy_kcal']['correct'])->toBe(2)
        ->and($stats['overall']['fields']['energy_kcal']['total'])->toBe(3)
        ->and($stats['overall']['fields']['fat_g']['correct'])->toBe(3)
        ->and($stats['per_image']['A']['runs'])->toBe(3)
        ->and($stats['per_image']['A']['fields']['energy_kcal'])->toBe(['pass' => 2, 'of' => 3])
        ->and($stats['per_image']['A']['all_fields_pass_runs'])->toBe(2)
        ->and($stats['unstable'])->toBe(['A energy_kcal 2/3']);
});

it('summarises each kind separately and keeps images apart', function () {
    $items = [
        runItem('R1', 'web_sample_id', 1),
        runItem('R1', 'web_sample_id', 2, ['sodium_mg' => 99]),
        runItem('S1', 'synthetic_id', 1),
        runItem('S1', 'synthetic_id', 2),
    ];

    $stats = (new ScanRunStats)->build($items);

    expect(array_keys($stats['by_kind']))->toBe(['web_sample_id', 'synthetic_id'])
        ->and($stats['by_kind']['web_sample_id']['fields']['sodium_mg'])->toMatchArray(['correct' => 1, 'total' => 2])
        ->and($stats['by_kind']['synthetic_id']['fields']['sodium_mg'])->toMatchArray(['correct' => 2, 'total' => 2])
        ->and($stats['overall']['fields']['sodium_mg'])->toMatchArray(['correct' => 3, 'total' => 4])
        ->and($stats['by_kind']['synthetic_id']['images'])->toBe(2)
        ->and(array_keys($stats['per_image']))->toBe(['R1', 'S1']);
});

it('reports provider errors, invalid outputs, abstentions and unsafe inferences', function () {
    $items = [
        runItem('A', 'real_id', 1, code: 'AI_OUTPUT_INVALID'),
        runItem('A', 'real_id', 2, code: 'AI_TIMEOUT'),
        runItem('A', 'real_id', 3, code: 'AI_TIMEOUT'),
        runItem('B', 'real_id', 1, code: 'OCR_UNREADABLE'),
        // truth has no sodium, the extraction invents one
        runItem('C', 'real_id', 1, ['sodium_mg' => 120], truth: ['nutrients' => ['energy_kcal' => 150.0, 'fat_g' => 8.0, 'sugars_total_g' => 12.0, 'protein_g' => 3.0, 'carbohydrate_g' => 20.0, 'sodium_mg' => null]]),
    ];

    $stats = (new ScanRunStats)->build($items);

    expect($stats['errors_by_code'])->toBe(['AI_OUTPUT_INVALID' => 1, 'AI_TIMEOUT' => 2])
        ->and($stats['overall']['errors'])->toBe(3)
        ->and($stats['overall']['invalid_output'])->toBe(1)
        ->and($stats['overall']['abstained'])->toBe(1)
        ->and($stats['overall']['false_abstentions'])->toBe(1)
        ->and($stats['overall']['unsafe_inferences'])->toBe(1);
});

it('does not call an image unstable when it fails every run', function () {
    $items = [runItem('A', 'real_id', 1, ['fat_g' => 1]), runItem('A', 'real_id', 2, ['fat_g' => 1])];

    expect((new ScanRunStats)->build($items)['unstable'])->toBe([]);
});

it('computes latency percentiles over all requests of all runs', function () {
    $items = [];
    foreach (range(1, 20) as $n) {
        $items[] = ['latency_ms' => $n * 100] + runItem('A', 'real_id', $n);
    }
    // runItem sets latency_ms = 1000 * run; the union above keeps the left side, i.e. n * 100
    $latency = (new ScanRunStats)->build($items)['overall']['latency_ms'];

    expect($latency)->toBe(['n' => 20, 'p50' => 1000, 'p95' => 1900, 'max' => 2000]);
});

it('does not score provider errors as model results, even when interleaved between valid runs', function () {
    $items = [
        runItem('A', 'real_id', 1),
        runItem('A', 'real_id', 2, code: 'AI_PROVIDER_ERROR'),
        runItem('A', 'real_id', 3, ['energy_kcal' => 15]),
    ];

    $stats = (new ScanRunStats)->build($items);
    $energy = $stats['overall']['fields']['energy_kcal'];

    expect($energy)->toMatchArray(['correct' => 1, 'total' => 2])
        ->and($stats['overall']['fields']['serving_size'])->toMatchArray(['correct' => 2, 'total' => 2])
        ->and($stats['overall']['images'])->toBe(3)
        ->and($stats['overall']['valid_readings'])->toBe(2)
        ->and($stats['overall']['provider_errors'])->toBe(1)
        ->and($stats['overall']['provider_errors_by_code'])->toBe(['AI_PROVIDER_ERROR' => 1])
        ->and($stats['by_kind']['real_id']['valid_readings'])->toBe(2)
        ->and($stats['per_image']['A']['runs'])->toBe(3)
        ->and($stats['per_image']['A']['valid_readings'])->toBe(2)
        ->and($stats['per_image']['A']['provider_errors'])->toBe(1)
        ->and($stats['per_image']['A']['fields']['energy_kcal'])->toBe(['pass' => 1, 'of' => 2])
        ->and($stats['per_image']['A']['all_fields_pass_runs'])->toBe(1)
        ->and($stats['unstable'])->toBe(['A energy_kcal 1/2']);
});

it('does not let a provider error count as a correct unknown or an unstable field', function () {
    $nullSodium = statsTruth(['nutrients' => ['energy_kcal' => 150.0, 'fat_g' => 8.0, 'sugars_total_g' => 12.0, 'protein_g' => 3.0, 'carbohydrate_g' => 20.0, 'sodium_mg' => null]]);
    $items = [
        runItem('N', 'real_id', 1, ['sodium_mg' => null], truth: $nullSodium),
        runItem('N', 'real_id', 2, code: 'AI_TIMEOUT', truth: $nullSodium),
    ];

    $stats = (new ScanRunStats)->build($items);

    expect($stats['overall']['fields']['sodium_mg'])->toMatchArray(['null_correct' => 1, 'null_total' => 1, 'correct' => 0, 'total' => 0])
        ->and($stats['per_image']['N']['fields']['sodium_mg'])->toBe(['pass' => 1, 'of' => 1])
        ->and($stats['unstable'])->toBe([]);
});

it('reports a group made only of provider errors with no scored fields and no latency', function () {
    $items = [runItem('A', 'real_id', 1, code: 'AI_PROVIDER_ERROR'), runItem('A', 'real_id', 2, code: 'AI_PROVIDER_ERROR')];

    $stats = (new ScanRunStats)->build($items);

    expect($stats['overall']['valid_readings'])->toBe(0)
        ->and($stats['overall']['provider_errors'])->toBe(2)
        ->and($stats['overall']['fields']['energy_kcal'])->toMatchArray(['correct' => 0, 'total' => 0, 'null_total' => 0])
        ->and($stats['overall']['latency_ms'])->toBe(['n' => 0, 'p50' => null, 'p95' => null, 'max' => null])
        ->and($stats['overall']['usage']['unknown_reports'])->toBe(0)
        ->and($stats['per_image']['A']['all_fields_pass_runs'])->toBe(0);
});

it('keeps an invalid model output as a scored failure, not a provider error', function () {
    $items = [runItem('A', 'real_id', 1), runItem('A', 'real_id', 2, code: 'AI_OUTPUT_INVALID')];

    $stats = (new ScanRunStats)->build($items);

    expect($stats['overall']['valid_readings'])->toBe(2)
        ->and($stats['overall']['provider_errors'])->toBe(0)
        ->and($stats['overall']['invalid_output'])->toBe(1)
        ->and($stats['overall']['fields']['energy_kcal'])->toMatchArray(['correct' => 1, 'total' => 2]);
});

it('leaves latency and token totals to valid readings only', function () {
    $ok = runItem('A', 'real_id', 1);
    $ok['usage'] = ['prompt' => 100, 'candidates' => 10, 'thoughts' => 5, 'total' => 115];
    $failed = runItem('A', 'real_id', 2, code: 'AI_PROVIDER_ERROR');
    $failed['latency_ms'] = 90000;

    $s = (new ScanRunStats)->build([$ok, $failed])['overall'];

    expect($s['latency_ms'])->toBe(['n' => 1, 'p50' => 1000, 'p95' => 1000, 'max' => 1000])
        ->and($s['usage'])->toBe(['prompt' => 100, 'candidates' => 10, 'thoughts' => 5, 'total' => 115, 'unknown_reports' => 0]);
});
