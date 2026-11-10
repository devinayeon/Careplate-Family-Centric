<?php

use App\Services\Ai\Scan\Decimal;
use App\Services\Ai\Scan\LabelNormalizer;

require_once __DIR__.'/RawLabelFactory.php';

it('rejects a unit that is invalid for the nutrient', function (string $key, ?string $unit) {
    expect(normalizeCode(rawLabel([$key => rawNutrient(5, $unit)])))->toBe('AI_OUTPUT_INVALID');
})->with([
    ['energy', 'g'], ['energy', 'mg'], ['energy', null],
    ['sodium', 'kcal'], ['sodium', 'kJ'], ['sodium', null],
    ['fat', 'mg'], ['fat', 'kcal'], ['sugars_total', 'mg'], ['protein', 'kJ'], ['carbohydrate', 'mg'], ['fat', 'oz'],
]);

it('requires evidence for every non-null value', function (?string $evidence) {
    expect(normalizeCode(rawLabel(['fat' => rawNutrient(8, 'g', 'exact', $evidence)])))->toBe('AI_OUTPUT_INVALID');
})->with([[null], [''], ['   ']]);

it('rejects negative, NaN and infinite values', function (float|int $value) {
    expect(normalizeCode(rawLabel(['fat' => rawNutrient($value, 'g')])))->toBe('AI_OUTPUT_INVALID');
})->with([[-1], [-0.01], [NAN], [INF], [-INF]]);

it('rejects negative or non-finite percent, serving and package values', function () {
    $serving = fn ($v) => ['value' => $v, 'unit' => 'g', 'household_text' => null];

    expect(normalizeCode(rawLabel(['fat' => rawNutrient(8, 'g', 'exact', '8 g', -5)])))->toBe('AI_OUTPUT_INVALID')
        ->and(normalizeCode(rawLabel([], ['serving_size' => $serving(-30)])))->toBe('AI_OUTPUT_INVALID')
        ->and(normalizeCode(rawLabel([], ['serving_size' => $serving(NAN)])))->toBe('AI_OUTPUT_INVALID')
        ->and(normalizeCode(rawLabel([], ['servings_per_package' => -1])))->toBe('AI_OUTPUT_INVALID');
});

it('rejects exact or less_than readings that carry no value', function (string $qualifier) {
    expect(normalizeCode(rawLabel(['fat' => rawNutrient(null, 'g', $qualifier, 'x')])))->toBe('AI_OUTPUT_INVALID');
})->with(['exact', 'less_than']);

it('rejects an unknown qualifier, basis or serving unit', function () {
    $serving = ['value' => 30, 'unit' => 'oz', 'household_text' => null];

    expect(normalizeCode(rawLabel(['fat' => rawNutrient(8, 'g', 'approx')])))->toBe('AI_OUTPUT_INVALID')
        ->and(normalizeCode(rawLabel([], ['label_basis' => 'per_pack'])))->toBe('AI_OUTPUT_INVALID')
        ->and(normalizeCode(rawLabel([], ['serving_size' => $serving])))->toBe('AI_OUTPUT_INVALID');
});

it('rejects a raw shape that does not conform instead of assuming it', function (callable $mutate) {
    expect(normalizeCode($mutate(rawLabel())))->toBe('AI_OUTPUT_INVALID');
})->with([
    'empty array' => [fn ($r) => []],
    'list instead of object' => [fn ($r) => [true, 'per_serving', false]],
    'list of nutrients' => [fn ($r) => array_replace($r, ['nutrients' => array_values($r['nutrients'])])],
    'missing top key' => [function ($r) {
        unset($r['needs_review']);

        return $r;
    }],
    'missing nutrient' => [function ($r) {
        unset($r['nutrients']['sodium']);

        return $r;
    }],
    'missing nutrient field' => [function ($r) {
        unset($r['nutrients']['fat']['evidence']);

        return $r;
    }],
    'nutrient not an object' => [fn ($r) => array_replace($r, ['nutrients' => array_replace($r['nutrients'], ['fat' => 8])])],
    'nutrient is a list' => [fn ($r) => array_replace($r, ['nutrients' => array_replace($r['nutrients'], ['fat' => [8, 'g', 'exact', 'x', null]])])],
    'nutrients is a string' => [fn ($r) => array_replace($r, ['nutrients' => 'n/a'])],
    'serving_size is a list' => [fn ($r) => array_replace($r, ['serving_size' => [30, 'g', null]])],
    'serving_size missing key' => [fn ($r) => array_replace($r, ['serving_size' => ['value' => 30, 'unit' => 'g']])],
    'readable is a string' => [fn ($r) => array_replace($r, ['readable' => 'yes'])],
    'needs_review is an int' => [fn ($r) => array_replace($r, ['needs_review' => 0])],
    'raw_value is a string' => [fn ($r) => array_replace_recursive($r, ['nutrients' => ['fat' => ['raw_value' => '8']]])],
    'evidence is an array' => [fn ($r) => array_replace_recursive($r, ['nutrients' => ['fat' => ['evidence' => ['x']]]])],
    'unexpected top key' => [fn ($r) => $r + ['salt' => 1]],
    'unexpected nutrient' => [fn ($r) => array_replace($r, ['nutrients' => $r['nutrients'] + ['salt' => $r['nutrients']['fat']]])],
    'unexpected nutrient field' => [fn ($r) => array_replace_recursive($r, ['nutrients' => ['fat' => ['note' => 'x']]])],
]);

it('rejects implausibly large values but accepts a value exactly at the cap', function () {
    $serving = fn ($v) => ['value' => $v, 'unit' => 'g', 'household_text' => null];

    expect(normalizeCode(rawLabel(['fat' => rawNutrient(1e30, 'g')])))->toBe('AI_OUTPUT_INVALID')
        ->and(normalizeCode(rawLabel(['fat' => rawNutrient(100001, 'g')])))->toBe('AI_OUTPUT_INVALID')
        ->and(normalizeCode(rawLabel(['fat' => rawNutrient(100000.5, 'g')])))->toBe('AI_OUTPUT_INVALID')
        ->and(normalizeCode(rawLabel(['fat' => rawNutrient(100000, 'g')])))->toBe('NO_EXCEPTION')
        ->and(normalizeCode(rawLabel([], ['serving_size' => $serving(1e30)])))->toBe('AI_OUTPUT_INVALID')
        ->and(normalizeCode(rawLabel([], ['serving_size' => $serving(100001)])))->toBe('AI_OUTPUT_INVALID')
        ->and(normalizeCode(rawLabel([], ['serving_size' => $serving(100000)])))->toBe('NO_EXCEPTION')
        ->and(normalizeCode(rawLabel(['fat' => rawNutrient(8, 'g', 'exact', '8 g', 1e30)])))->toBe('AI_OUTPUT_INVALID')
        ->and(normalizeCode(rawLabel(['fat' => rawNutrient(8, 'g', 'exact', '8 g', 1001)])))->toBe('AI_OUTPUT_INVALID')
        ->and(normalizeCode(rawLabel(['fat' => rawNutrient(8, 'g', 'exact', '8 g', 1000)])))->toBe('NO_EXCEPTION')
        ->and(normalizeCode(rawLabel([], ['servings_per_package' => 1e30])))->toBe('AI_OUTPUT_INVALID')
        ->and(normalizeCode(rawLabel([], ['servings_per_package' => 1001])))->toBe('AI_OUTPUT_INVALID')
        ->and(normalizeCode(rawLabel([], ['servings_per_package' => 1000])))->toBe('NO_EXCEPTION');
});

it('keeps the largest accepted values exact in the public numbers', function () {
    $r = (new LabelNormalizer)->normalize(rawLabel(
        ['sodium' => rawNutrient(100000, 'g')],
        ['serving_size' => ['value' => 100000, 'unit' => 'g', 'household_text' => null]],
    ));

    expect($r['normalized_per_serving']['sodium_mg'])->toBe(100000000)
        ->and(Decimal::toNumber('99999999999999999999999'))->toBeFloat()->toBeGreaterThan(9.9e22);
});
