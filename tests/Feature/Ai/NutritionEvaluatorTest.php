<?php

use App\Exceptions\Ai\AiException;
use App\Services\Ai\Scan\LabelNormalizer;
use App\Services\Ai\Scan\NutritionEvaluator;

require_once __DIR__.'/../../Unit/Ai/RawLabelFactory.php';

function evaluateLabel(array $nutrients = [], array $top = []): array
{
    $normalized = (new LabelNormalizer)->normalize(rawLabel($nutrients, $top));

    return (new NutritionEvaluator)->evaluate($normalized);
}

/** Complete per_serving label with explicit sugar (g), sodium (mg) and fat (g). */
function evaluateWith(string|int|float $sugar, string|int|float $sodium, string|int|float $fat): array
{
    return evaluateLabel([
        'sugars_total' => rawNutrient($sugar + 0, 'g'),
        'sodium' => rawNutrient($sodium + 0, 'mg'),
        'fat' => rawNutrient($fat + 0, 'g'),
    ]);
}

function approvePolicy(): void
{
    config(['nutrition.policy.status' => 'approved']);
}

it('shows contributions but no flags while the policy is provisional (the provisional example)', function () {
    $r = evaluateLabel();
    $a = $r['assessment'];

    expect($a['policy'])->toBe(['id' => 'careplate-ggl-screening-draft-v1', 'status' => 'provisional', 'scope' => 'general_adult_educational_screening'])
        ->and($a['daily_reference_contribution_percent'])->toBe(['sugars_total' => 24, 'sodium' => 22.5, 'fat_total' => 11.94])
        ->and($a['flags'])->toBe([])
        ->and($a['verdict'])->toBe('policy_pending_validation')
        ->and($a['summary'])->toBe('Kontribusi gula 24%, natrium 22,5%, dan lemak 11,94% dari pembanding edukasi per sajian. Klasifikasi menunggu validasi kebijakan.')
        ->and($r['warnings'])->toContain('Pembanding edukasi dewasa umum; bukan %AKG label atau kebutuhan personal.')
        ->and(implode(' ', $r['warnings']))->toContain('belum disetujui');
});

it('keeps flags empty for any provisional or disabled policy, even with huge values', function (string $status) {
    config(['nutrition.policy.status' => $status]);

    $a = evaluateWith(100, 5000, 200)['assessment'];

    expect($a['flags'])->toBe([])
        ->and($a['verdict'])->toBe('policy_pending_validation')
        ->and($a['policy']['status'])->toBe($status)
        ->and($a['daily_reference_contribution_percent']['sugars_total'])->toBe(200);
})->with(['provisional', 'disabled']);

it('flags at exactly 20 percent of the reference and not below it (approved policy)', function (float $sugar, float $sodium, float $fat, array $flags) {
    approvePolicy();

    expect(evaluateWith($sugar, $sodium, $fat)['assessment']['flags'])->toBe($flags);
})->with([
    'exact 10 g / 400 mg / 13.4 g' => [10, 400, 13.4, ['high_sugar', 'high_sodium', 'high_fat']],
    'just under' => [9.99, 399.9, 13.39, []],
    'sugar only' => [10, 100, 1, ['high_sugar']],
    'sodium only' => [1, 400, 1, ['high_sodium']],
    'fat only' => [1, 100, 13.4, ['high_fat']],
]);

it('reports high_flags, no_high_flag or insufficient_data with an approved policy', function () {
    approvePolicy();

    $high = evaluateWith(12, 100, 1)['assessment'];
    expect($high['verdict'])->toBe('high_flags')->and($high['flags'])->toBe(['high_sugar']);

    $none = evaluateWith(5, 100, 1)['assessment'];
    expect($none['verdict'])->toBe('no_high_flag')->and($none['flags'])->toBe([])
        ->and($none['summary'])->not->toContain('Seimbang')->and($none['summary'])->not->toContain('aman');

    $partial = evaluateLabel(['sugars_total' => rawUnknown(), 'sodium' => rawUnknown()])['assessment'];
    expect($partial['verdict'])->toBe('insufficient_data')
        ->and($partial['flags'])->toBe([])
        ->and($partial['daily_reference_contribution_percent'])->toBe(['sugars_total' => null, 'sodium' => null, 'fat_total' => 11.94])
        ->and($partial['summary'])->toBe('Gula dan natrium belum terbaca. Ambil foto tabel yang lebih jelas.');
});

it('still flags what is known when other data is missing, but never calls partial data clear', function () {
    approvePolicy();

    $a = evaluateLabel(['sugars_total' => rawNutrient(12, 'g'), 'sodium' => rawUnknown()])['assessment'];

    expect($a['flags'])->toBe(['high_sugar'])
        ->and($a['verdict'])->toBe('high_flags')
        ->and($a['daily_reference_contribution_percent']['sodium'])->toBeNull();
});

it('does not publish a no_high_flag from a less_than reading', function () {
    approvePolicy();

    $a = evaluateLabel(['sugars_total' => rawNutrient(0.5, 'g', 'less_than'), 'sodium' => rawNutrient(100, 'mg'), 'fat' => rawNutrient(1, 'g')])['assessment'];

    expect($a['verdict'])->toBe('insufficient_data')
        ->and($a['daily_reference_contribution_percent']['sugars_total'])->toBeNull();
});

it('keeps unknown as null, never 0, and exact 0 as 0', function () {
    $a = evaluateLabel(['sugars_total' => rawNutrient(0, 'g'), 'sodium' => rawUnknown()])['assessment'];

    expect($a['daily_reference_contribution_percent']['sugars_total'])->toBe(0)
        ->and($a['daily_reference_contribution_percent']['sodium'])->toBeNull();
});

it('returns no contributions and insufficient_data on a global conflict', function () {
    approvePolicy();

    $a = evaluateLabel([], ['needs_review' => true])['assessment'];

    expect($a['daily_reference_contribution_percent'])->toBe(['sugars_total' => null, 'sodium' => null, 'fat_total' => null])
        ->and($a['flags'])->toBe([])
        ->and($a['verdict'])->toBe('insufficient_data')
        ->and($a['summary'])->not->toBe('');
});

it('derives contributions from per_100g labels through the normalized per-serving values', function () {
    $r = evaluateLabel(['sugars_total' => rawNutrient(20, 'g'), 'fat' => rawNutrient(10, 'g'), 'sodium' => rawNutrient(500, 'mg')], ['label_basis' => 'per_100g']);

    expect($r['assessment']['daily_reference_contribution_percent'])->toBe(['sugars_total' => 12, 'sodium' => 7.5, 'fat_total' => 4.48]);
});

it('uses the configured reference and threshold, not hard-coded values', function () {
    approvePolicy();
    config(['nutrition.daily_reference.sugars_total_g' => '25']);

    $a = evaluateWith(5, 100, 1)['assessment'];

    expect($a['daily_reference_contribution_percent']['sugars_total'])->toBe(20)
        ->and($a['flags'])->toBe(['high_sugar']);
});

it('refuses an invalid policy configuration', function (string $key, mixed $value) {
    config([$key => $value]);

    expect(fn () => evaluateLabel())->toThrow(AiException::class);
    try {
        evaluateLabel();
    } catch (AiException $e) {
        expect($e->errorCode)->toBe('POLICY_CONFIG_INVALID')->and($e->status)->toBe(503);
    }
})->with([
    ['nutrition.policy.status', 'maybe'],
    ['nutrition.daily_reference.sodium_mg', '0'],
    ['nutrition.daily_reference.fat_g', 'abc'],
    ['nutrition.daily_reference.sugars_total_g', null],
    ['nutrition.screening.threshold_percent', '-1'],
    ['nutrition.screening.comparison', 'gt'],
]);

it('never uses forbidden wording in any summary or warning', function () {
    foreach (['provisional', 'approved'] as $status) {
        config(['nutrition.policy.status' => $status]);
        foreach ([evaluateLabel(), evaluateWith(1, 10, 1), evaluateLabel([], ['needs_review' => true])] as $r) {
            $text = strtolower($r['assessment']['summary'].' '.implode(' ', $r['warnings']));
            expect(preg_match('/(?<![a-z])(seimbang|sehat|aman)(?![a-z])/', $text))->toBe(0);
        }
    }
});
