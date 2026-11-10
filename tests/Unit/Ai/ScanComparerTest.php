<?php

use App\Services\Ai\Eval\ScanComparer;

function evalTruth(array $over = []): array
{
    return array_replace([
        'expected_outcome' => 'extract',
        'label_basis' => 'per_serving',
        'serving_size' => ['value' => 30.0, 'unit' => 'g'],
        'nutrients' => [
            'energy_kcal' => 150.0, 'fat_g' => 8.0, 'sugars_total_g' => 12.0,
            'protein_g' => 3.0, 'carbohydrate_g' => 20.0, 'sodium_mg' => 450.0,
        ],
        'less_than' => [],
    ], $over);
}

function evalResponse(array $nutrients = [], array $over = []): array
{
    $values = array_replace([
        'energy_kcal' => 150, 'fat_g' => 8, 'sugars_total_g' => 12,
        'protein_g' => 3, 'carbohydrate_g' => 20, 'sodium_mg' => 450,
    ], $nutrients);
    $out = [];
    foreach ($values as $key => $value) {
        $out[$key] = ['value' => $value, 'qualifier' => $value === null ? 'unknown' : 'exact'];
    }

    return array_replace_recursive([
        'extraction' => [
            'label_basis' => 'per_serving',
            'serving_size' => ['value' => 30, 'unit' => 'g', 'household_text' => null],
            'nutrients' => $out,
        ],
    ], $over);
}

it('marks every field a match when the extraction equals the truth', function () {
    $r = (new ScanComparer)->compare(evalTruth(), evalResponse(), null);

    expect($r['outcome'])->toBe('extracted')
        ->and($r['abstained'])->toBeFalse()
        ->and($r['invalid_output'])->toBeFalse()
        ->and($r['unsafe_count'])->toBe(0)
        ->and(array_unique(array_column($r['fields'], 'result')))->toBe(['match'])
        ->and(array_keys($r['fields']))->toContain('energy_kcal', 'sodium_mg', 'serving_size', 'label_basis');
});

it('compares values numerically, not as strings', function () {
    $r = (new ScanComparer)->compare(evalTruth(), evalResponse(['fat_g' => 8.0, 'protein_g' => 3.0000001]), null);

    expect($r['fields']['fat_g']['result'])->toBe('match')
        ->and($r['fields']['protein_g']['result'])->toBe('match');
});

it('flags a wrong value as mismatch and a missing value as missing', function () {
    $r = (new ScanComparer)->compare(evalTruth(), evalResponse(['sodium_mg' => 45, 'fat_g' => null]), null);

    expect($r['fields']['sodium_mg'])->toMatchArray(['result' => 'mismatch', 'truth' => 450.0, 'got' => 45])
        ->and($r['fields']['fat_g']['result'])->toBe('missing');
});

it('treats exact zero as a value, so null for a printed zero is missing', function () {
    $truth = evalTruth(['nutrients' => ['fat_g' => 0.0] + evalTruth()['nutrients']]);

    expect((new ScanComparer)->compare($truth, evalResponse(['fat_g' => 0]), null)['fields']['fat_g']['result'])->toBe('match')
        ->and((new ScanComparer)->compare($truth, evalResponse(['fat_g' => null]), null)['fields']['fat_g']['result'])->toBe('missing');
});

it('counts a non-null value where the truth is null as an unsafe inference', function () {
    $truth = evalTruth(['nutrients' => ['sodium_mg' => null] + evalTruth()['nutrients']]);
    $r = (new ScanComparer)->compare($truth, evalResponse(['sodium_mg' => 5]), null);

    expect($r['fields']['sodium_mg']['result'])->toBe('unsafe')
        ->and($r['unsafe_count'])->toBe(1);

    $ok = (new ScanComparer)->compare($truth, evalResponse(['sodium_mg' => null]), null);
    expect($ok['fields']['sodium_mg']['result'])->toBe('correct_null')
        ->and($ok['unsafe_count'])->toBe(0);
});

it('checks the qualifier against the truth', function () {
    $truth = evalTruth(['less_than' => ['sugars_total_g']]);
    $response = evalResponse();
    $response['extraction']['nutrients']['sugars_total_g']['qualifier'] = 'less_than';

    $r = (new ScanComparer)->compare($truth, $response, null);
    expect($r['fields']['sugars_total_g']['result'])->toBe('match');

    $wrong = (new ScanComparer)->compare($truth, evalResponse(), null);
    expect($wrong['fields']['sugars_total_g']['result'])->toBe('mismatch');
});

it('scores serving size on value and unit together, and basis on its own', function () {
    $r = (new ScanComparer)->compare(evalTruth(), evalResponse([], [
        'extraction' => ['label_basis' => 'per_100g', 'serving_size' => ['value' => 30, 'unit' => 'ml']],
    ]), null);

    expect($r['fields']['serving_size']['result'])->toBe('mismatch')
        ->and($r['fields']['label_basis']['result'])->toBe('mismatch');
});

it('does not score basis when the truth has none and flags a serving read where truth is null as unsafe', function () {
    $truth = evalTruth(['label_basis' => null, 'serving_size' => ['value' => null, 'unit' => null]]);
    $r = (new ScanComparer)->compare($truth, evalResponse(), null);

    expect($r['fields']['label_basis']['result'])->toBe('not_scored')
        ->and($r['fields']['serving_size']['result'])->toBe('unsafe');
});

it('reads OCR_UNREADABLE as abstention: missing for known fields, correct_null for unknown ones', function () {
    $truth = evalTruth(['nutrients' => ['sodium_mg' => null] + evalTruth()['nutrients']]);
    $r = (new ScanComparer)->compare($truth, null, 'OCR_UNREADABLE');

    expect($r['outcome'])->toBe('unreadable')
        ->and($r['abstained'])->toBeTrue()
        ->and($r['invalid_output'])->toBeFalse()
        ->and($r['fields']['energy_kcal']['result'])->toBe('missing')
        ->and($r['fields']['sodium_mg']['result'])->toBe('correct_null');
});

it('records AI_OUTPUT_INVALID as an invalid output and other codes as errors', function () {
    $invalid = (new ScanComparer)->compare(evalTruth(), null, 'AI_OUTPUT_INVALID');
    $timeout = (new ScanComparer)->compare(evalTruth(), null, 'AI_TIMEOUT');

    expect($invalid['outcome'])->toBe('error')
        ->and($invalid['invalid_output'])->toBeTrue()
        ->and($invalid['error_code'])->toBe('AI_OUTPUT_INVALID')
        ->and($timeout['invalid_output'])->toBeFalse()
        ->and($timeout['error_code'])->toBe('AI_TIMEOUT');
});

it('treats abstention on a negative image as correct and any extraction there as unsafe', function () {
    $truth = evalTruth([
        'expected_outcome' => 'unreadable', 'label_basis' => null,
        'serving_size' => ['value' => null, 'unit' => null],
        'nutrients' => array_fill_keys(array_keys(evalTruth()['nutrients']), null),
    ]);

    $good = (new ScanComparer)->compare($truth, null, 'OCR_UNREADABLE');
    expect($good['negative_correct'])->toBeTrue()
        ->and($good['unsafe_count'])->toBe(0);

    $bad = (new ScanComparer)->compare($truth, evalResponse(), null);
    expect($bad['negative_correct'])->toBeFalse()
        ->and($bad['unsafe_count'])->toBeGreaterThan(0);
});

it('summarises n/N per field, abstentions, invalid outputs, unsafe inferences, latency and usage', function () {
    $c = new ScanComparer;
    $truth = evalTruth();
    $items = [
        ['split' => 'dev', 'latency_ms' => 1000, 'usage' => ['prompt' => 100, 'candidates' => 10, 'thoughts' => 5, 'total' => 115]]
        + $c->compare($truth, evalResponse(), null),
        ['split' => 'holdout', 'latency_ms' => 3000, 'usage' => ['prompt' => 200, 'candidates' => 20, 'thoughts' => null, 'total' => null]]
        + $c->compare($truth, evalResponse(['sodium_mg' => 1]), null),
        ['split' => 'holdout', 'latency_ms' => 2000, 'usage' => null]
        + $c->compare($truth, null, 'OCR_UNREADABLE'),
    ];

    $s = $c->summarize($items);

    expect($s['images'])->toBe(3)
        ->and($s['fields']['sodium_mg'])->toMatchArray(['correct' => 1, 'total' => 3])
        ->and($s['fields']['fat_g'])->toMatchArray(['correct' => 2, 'total' => 3])
        ->and($s['abstained'])->toBe(1)
        ->and($s['invalid_output'])->toBe(0)
        ->and($s['unsafe_inferences'])->toBe(0)
        ->and($s['latency_ms'])->toMatchArray(['p50' => 2000, 'max' => 3000, 'n' => 3])
        ->and($s['usage'])->toMatchArray(['prompt' => 300, 'candidates' => 30, 'thoughts' => 5, 'total' => 115, 'unknown_reports' => 2]);
});
