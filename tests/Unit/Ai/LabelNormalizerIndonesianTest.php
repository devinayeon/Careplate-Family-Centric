<?php

use App\Services\Ai\Scan\LabelNormalizer;

require_once __DIR__.'/RawLabelFactory.php';

/** Provider outputs shaped like the readings of BPOM "Informasi Nilai Gizi" labels (synthetic, no network). */
function idNorm(array $raw): array
{
    return (new LabelNormalizer)->normalize($raw);
}

/** SYN-01 style: Takaran Saji 1 bungkus (25 g), decimal commas, Energi Total 120 kkal, Garam (Natrium) 120 mg, %AKG. */
function idLabel(array $nutrients = [], array $top = []): array
{
    return rawLabel(array_replace([
        'energy' => rawNutrient(120, 'kcal', 'exact', 'Energi Total 120 kkal', 6),
        'fat' => rawNutrient(5.5, 'g', 'exact', 'Lemak Total 5,5 g', 8),
        'sugars_total' => rawNutrient(8.5, 'g', 'exact', 'Gula 8,5 g'),
        'protein' => rawNutrient(1.5, 'g', 'exact', 'Protein 1,5 g', 3),
        'carbohydrate' => rawNutrient(17, 'g', 'exact', 'Karbohidrat Total 17 g', 5),
        'sodium' => rawNutrient(120, 'mg', 'exact', 'Garam (Natrium) 120 mg', 8),
    ], $nutrients), array_replace([
        'serving_size' => ['value' => 25, 'unit' => 'g', 'household_text' => '1 bungkus'],
        'servings_per_package' => 4,
    ], $top));
}

it('reads a BPOM label with decimal commas, kkal and a %AKG column into per-serving numbers', function () {
    $r = idNorm(idLabel());

    expect($r['extraction_status'])->toBe('complete')
        ->and($r['normalized_per_serving'])->toBe([
            'energy_kcal' => 120, 'fat_g' => 5.5, 'sugars_total_g' => 8.5, 'protein_g' => 1.5, 'carbohydrate_g' => 17, 'sodium_mg' => 120,
        ])
        ->and($r['extraction']['nutrients']['fat_g']['label_percent_akg'])->toBe(8)
        ->and($r['extraction']['nutrients']['fat_g']['evidence'])->toBe('Lemak Total 5,5 g')
        ->and($r['extraction']['servings_per_package'])->toBe(4);
});

it('keeps Garam (Natrium) as sodium but never turns a Garam-only row into sodium', function (string $evidence, int|float $value, string $unit, ?int $expectedMg) {
    $r = idNorm(idLabel(['sodium' => rawNutrient($value, $unit, 'exact', $evidence)]));

    expect($r['normalized_per_serving']['sodium_mg'])->toBe($expectedMg);
})->with([
    ['Garam (Natrium) 120 mg', 120, 'mg', 120],
    ['Garam (Natrium)/Salt (Sodium) 160 mg', 160, 'mg', 160],
    ['Natrium 30 mg', 30, 'mg', 30],
    ['Garam 0,3 g', 0.3, 'g', null],
    ['Salt 0,3 g', 0.3, 'g', null],
    ['Garam beryodium 0,3 g', 0.3, 'g', null],
    ['Zout/Sel < 0,01 g', 0.01, 'g', null],
    ['Sel 0,3 g', 0.3, 'g', null],
    ['Salz 0,3 g', 0.3, 'g', null],
    ['Sodio (sal) 120 mg', 120, 'mg', 120],
    ['Sódio (sal) 120 mg', 120, 'mg', 120],
    ['Sal 0,3 g', 0.3, 'g', null],
]);

it('converts a kJ energy reading to kcal and keeps a printed kkal reading as is', function () {
    $kj = idNorm(idLabel(['energy' => rawNutrient(356, 'kJ', 'exact', '356 kJ')]));
    $kcal = idNorm(idLabel(['energy' => rawNutrient(85, 'kcal', 'exact', '356 kJ (85 kkal)')]));

    expect($kj['extraction']['nutrients']['energy_kcal']['value'])->toBe(85.086)
        ->and($kcal['extraction']['nutrients']['energy_kcal']['value'])->toBe(85)
        ->and($kcal['extraction']['nutrients']['energy_kcal']['unit'])->toBe('kcal');
});

it('keeps a less-than sugar bound without producing an exact per-serving value', function () {
    $r = idNorm(idLabel(['sugars_total' => rawNutrient(1, 'g', 'less_than', 'Gula < 1 g'), 'protein' => rawNutrient(0, 'g', 'exact', 'Protein 0 g', 0)]));

    expect($r['extraction']['nutrients']['sugars_total_g'])->toMatchArray(['value' => 1, 'qualifier' => 'less_than'])
        ->and($r['normalized_per_serving']['sugars_total_g'])->toBeNull()
        ->and($r['normalized_per_serving']['protein_g'])->toBe(0)
        ->and($r['extraction_status'])->toBe('partial');
});

it('keeps a household-only serving (no grams) as unknown and the label partial', function () {
    $r = idNorm(idLabel([], ['serving_size' => ['value' => null, 'unit' => null, 'household_text' => '2 sendok makan']]));

    expect($r['extraction']['serving_size'])->toBe(['value' => null, 'unit' => null, 'household_text' => '2 sendok makan'])
        ->and($r['extraction_status'])->toBe('partial')
        ->and($r['extraction']['nutrients']['energy_kcal']['value'])->toBe(120);
});

it('does not derive per-serving values from a per 100 g label without a serving size', function () {
    $r = idNorm(idLabel([], ['label_basis' => 'per_100g', 'serving_size' => ['value' => null, 'unit' => null, 'household_text' => null]]));

    expect(array_filter($r['normalized_per_serving'], fn ($v) => $v !== null))->toBe([])
        ->and($r['extraction']['nutrients']['energy_kcal']['value'])->toBe(120)
        ->and($r['warnings'])->toContain('Takaran saji tidak tersedia; nilai per sajian tidak dihitung.');
});

it('derives per-serving values from a dual-column label by the chosen basis only', function () {
    // Per 100 g column read for a 40 g serving: 17.5 g fat per 100 g is 7 g per serving.
    $r = idNorm(idLabel(['fat' => rawNutrient(17.5, 'g', 'exact', 'Lemak Total 17,5 g')], [
        'label_basis' => 'per_100g', 'serving_size' => ['value' => 40, 'unit' => 'g', 'household_text' => null],
    ]));

    expect($r['normalized_per_serving']['fat_g'])->toBe(7);
});
