<?php

use App\Services\Ai\Scan\LabelNormalizer;

require_once __DIR__.'/RawLabelFactory.php';

function norm(array $raw): array
{
    return (new LabelNormalizer)->normalize($raw);
}

function nonNull(array $values): array
{
    return array_filter($values, fn ($v) => $v !== null);
}

it('normalizes the provisional example exactly as the public contract shows', function () {
    $r = norm(rawLabel());

    expect($r['extraction_status'])->toBe('complete')
        ->and($r['extraction']['label_basis'])->toBe('per_serving')
        ->and($r['extraction']['serving_size'])->toBe(['value' => 30, 'unit' => 'g', 'household_text' => '3 keping'])
        ->and($r['extraction']['servings_per_package'])->toBe(2)
        ->and($r['extraction']['needs_review'])->toBeFalse()
        ->and(array_keys($r['extraction']['nutrients']))->toBe(['energy_kcal', 'fat_g', 'sugars_total_g', 'protein_g', 'carbohydrate_g', 'sodium_mg'])
        ->and($r['extraction']['nutrients']['sodium_mg'])->toBe([
            'value' => 450, 'qualifier' => 'exact', 'unit' => 'mg', 'evidence' => 'label 0.45 g', 'label_percent_akg' => null,
        ])
        ->and($r['extraction']['nutrients']['energy_kcal']['unit'])->toBe('kcal')
        ->and($r['normalized_per_serving'])->toBe([
            'energy_kcal' => 150, 'fat_g' => 8, 'sugars_total_g' => 12, 'protein_g' => 3, 'carbohydrate_g' => 20, 'sodium_mg' => 450,
        ])
        ->and($r['values']['sodium_mg'])->toBe('450')
        ->and($r['values']['sugars_total_g'])->toBe('12')
        ->and($r['warnings'])->toBe([]);
});

it('converts kJ to kcal by dividing by 4.184', function () {
    expect(norm(rawLabel(['energy' => rawNutrient(627.6, 'kJ')]))['normalized_per_serving']['energy_kcal'])->toBe(150);
    $r = norm(rawLabel(['energy' => rawNutrient(1000, 'kJ')]));
    expect($r['normalized_per_serving']['energy_kcal'])->toBe(239.0057)
        ->and($r['extraction']['nutrients']['energy_kcal']['unit'])->toBe('kcal')
        ->and($r['extraction']['nutrients']['energy_kcal']['value'])->toBe(239.0057);
});

it('keeps sodium in mg and converts sodium g to mg', function () {
    expect(norm(rawLabel(['sodium' => rawNutrient(450, 'mg')]))['normalized_per_serving']['sodium_mg'])->toBe(450)
        ->and(norm(rawLabel(['sodium' => rawNutrient(1.2, 'g')]))['normalized_per_serving']['sodium_mg'])->toBe(1200);
});

it('computes per-serving from per_100g by grams/100 with BCMath', function () {
    $r = norm(rawLabel([
        'energy' => rawNutrient(250, 'kcal'), 'sugars_total' => rawNutrient(10, 'g'),
        'protein' => rawNutrient(0.3, 'g'), 'sodium' => rawNutrient(600, 'mg'),
    ], ['label_basis' => 'per_100g']));

    expect($r['normalized_per_serving'])->toMatchArray(['energy_kcal' => 75, 'sugars_total_g' => 3, 'protein_g' => 0.09, 'sodium_mg' => 180])
        ->and($r['extraction']['nutrients']['sugars_total_g']['value'])->toBe(10)
        ->and($r['extraction_status'])->toBe('complete');
});

it('computes per-serving from per_100ml by ml/100', function () {
    $r = norm(rawLabel(['sugars_total' => rawNutrient(8.4, 'g')], [
        'label_basis' => 'per_100ml', 'serving_size' => ['value' => 250, 'unit' => 'ml', 'household_text' => null],
    ]));

    expect($r['normalized_per_serving']['sugars_total_g'])->toBe(21);
});

it('never converts between ml and g', function (string $basis, string $unit) {
    $r = norm(rawLabel([], [
        'label_basis' => $basis, 'serving_size' => ['value' => 250, 'unit' => $unit, 'household_text' => null],
    ]));

    expect(nonNull($r['normalized_per_serving']))->toBe([])
        ->and(nonNull($r['values']))->toBe([])
        ->and($r['extraction_status'])->toBe('partial')
        ->and($r['extraction']['nutrients']['fat_g']['value'])->toBe(8)
        ->and($r['warnings'])->not->toBe([]);
})->with([['per_100g', 'ml'], ['per_100ml', 'g']]);

it('leaves per-serving null when a per_100g label has no serving size', function () {
    $r = norm(rawLabel([], [
        'label_basis' => 'per_100g', 'serving_size' => ['value' => null, 'unit' => null, 'household_text' => '1 bungkus'],
    ]));

    expect($r['normalized_per_serving']['fat_g'])->toBeNull()
        ->and($r['extraction']['serving_size']['value'])->toBeNull()
        ->and($r['extraction_status'])->toBe('partial');
});

it('treats a zero serving size as unknown', function () {
    $serving = ['value' => 0, 'unit' => 'g', 'household_text' => null];
    $r = norm(rawLabel([], ['label_basis' => 'per_100g', 'serving_size' => $serving]));

    expect($r['extraction']['serving_size']['value'])->toBeNull()
        ->and($r['normalized_per_serving']['fat_g'])->toBeNull();
});

it('leaves all normalized values null when the basis is unknown', function () {
    $r = norm(rawLabel([], ['label_basis' => 'unknown']));

    expect(nonNull($r['normalized_per_serving']))->toBe([])
        ->and($r['extraction_status'])->toBe('partial')
        ->and($r['extraction']['label_basis'])->toBe('unknown');
});

it('keeps a less_than bound but never produces an exact normalized value', function () {
    $r = norm(rawLabel(['sugars_total' => rawNutrient(0.5, 'g', 'less_than')]));

    expect($r['extraction']['nutrients']['sugars_total_g'])->toMatchArray(['value' => 0.5, 'qualifier' => 'less_than', 'unit' => 'g'])
        ->and($r['normalized_per_serving']['sugars_total_g'])->toBeNull()
        ->and($r['values']['sugars_total_g'])->toBeNull()
        ->and($r['normalized_per_serving']['fat_g'])->toBe(8)
        ->and($r['extraction_status'])->toBe('partial');
});

it('keeps a printed zero as exact 0 and never turns unknown into 0', function () {
    $r = norm(rawLabel(['sugars_total' => rawNutrient(0, 'g'), 'sodium' => rawUnknown()]));

    expect($r['normalized_per_serving']['sugars_total_g'])->toBe(0)
        ->and($r['values']['sugars_total_g'])->toBe('0')
        ->and($r['normalized_per_serving']['sodium_mg'])->toBeNull()
        ->and($r['extraction']['nutrients']['sodium_mg'])->toBe([
            'value' => null, 'qualifier' => 'unknown', 'unit' => 'mg', 'evidence' => null, 'label_percent_akg' => null,
        ])
        ->and($r['extraction_status'])->toBe('partial');
});

it('reports a missing column as partial while the other columns stay usable', function () {
    $r = norm(rawLabel(['sugars_total' => rawUnknown(), 'sodium' => rawUnknown()]));

    expect($r['extraction_status'])->toBe('partial')
        ->and($r['extraction']['needs_review'])->toBeFalse()
        ->and($r['normalized_per_serving'])->toBe([
            'energy_kcal' => 150, 'fat_g' => 8, 'sugars_total_g' => null, 'protein_g' => 3, 'carbohydrate_g' => 20, 'sodium_mg' => null,
        ]);
});

it('treats a salt-only sodium reading as unknown, since salt is not sodium', function () {
    $r = norm(rawLabel(['sodium' => rawNutrient(1, 'g', 'exact', 'Garam 1 g')]));

    expect($r['normalized_per_serving']['sodium_mg'])->toBeNull()
        ->and($r['extraction']['nutrients']['sodium_mg']['qualifier'])->toBe('unknown')
        ->and($r['extraction']['nutrients']['sodium_mg']['value'])->toBeNull()
        ->and($r['warnings'])->not->toBe([]);

    $ok = norm(rawLabel(['sodium' => rawNutrient(450, 'mg', 'exact', 'Natrium (garam) 450 mg')]));
    expect($ok['normalized_per_serving']['sodium_mg'])->toBe(450);
});

it('nulls every normalized value on a global conflict but keeps the readings', function () {
    $r = norm(rawLabel([], ['needs_review' => true]));

    expect($r['extraction']['needs_review'])->toBeTrue()
        ->and($r['extraction_status'])->toBe('partial')
        ->and(nonNull($r['normalized_per_serving']))->toBe([])
        ->and(nonNull($r['values']))->toBe([])
        ->and($r['extraction']['nutrients']['fat_g']['value'])->toBe(8)
        ->and($r['warnings'])->not->toBe([]);
});

it('rejects an unreadable label with OCR_UNREADABLE', function () {
    $unknown = array_fill_keys(['energy', 'fat', 'sugars_total', 'protein', 'carbohydrate', 'sodium'], rawUnknown());

    expect(normalizeCode(rawLabel($unknown, ['readable' => false])))->toBe('OCR_UNREADABLE')
        ->and(normalizeCode(rawLabel($unknown)))->toBe('OCR_UNREADABLE')
        ->and(normalizeCode(rawLabel($unknown, ['needs_review' => true])))->toBe('OCR_UNREADABLE')
        ->and(normalizeCode(rawLabel([], ['readable' => false])))->toBe('OCR_UNREADABLE');
});

it('truncates evidence and household text to the public 120 character limit', function () {
    $r = norm(rawLabel(['fat' => rawNutrient(8, 'g', 'exact', str_repeat('é', 200))], [
        'serving_size' => ['value' => 30, 'unit' => 'g', 'household_text' => str_repeat('a', 300)],
    ]));

    expect(mb_strlen($r['extraction']['nutrients']['fat_g']['evidence']))->toBe(120)
        ->and(mb_strlen($r['extraction']['serving_size']['household_text']))->toBe(120);
});

it('passes label_percent_akg through as a number', function () {
    $r = norm(rawLabel(['fat' => rawNutrient(8, 'g', 'exact', '8 g', 12)]));

    expect($r['extraction']['nutrients']['fat_g']['label_percent_akg'])->toBe(12);
});
