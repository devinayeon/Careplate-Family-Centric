<?php

use App\Models\Food;
use App\Models\FoodSubmission;
use App\Services\Ai\Foods\UsdaFoundationImporter;

require_once __DIR__.'/FoundationFixture.php';

// Every food here is SYNTHETIC (see FoundationFixture.php); nothing touches the network.

function synImport(?array $foods = null, ?array $curation = null): array
{
    return app(UsdaFoundationImporter::class)->import($curation ?? synCuration(), $foods ?? synFoods(), synSnapshot());
}

function synRow(array $report, string $candidate): array
{
    return collect($report['rows'])->firstWhere('candidate_id', $candidate);
}

function synSubmission(string $fdcId): FoodSubmission
{
    return FoodSubmission::where('source_provider', 'USDA_FDC')->where('source_id', $fdcId)->firstOrFail();
}

it('matches raw and cooked states without swapping them', function () {
    $report = synImport();

    expect(synRow($report, 'FOOD-002'))->toMatchArray(['status' => 'created', 'fdc_id' => '900001'])
        ->and(synRow($report, 'FOOD-001'))->toMatchArray(['status' => 'created', 'fdc_id' => '900002']);
    expect(synSubmission('900001')->candidate['nutrients']['protein_g'])->toBe('7.5')
        ->and(synSubmission('900002')->candidate['nutrients']['protein_g'])->toBe('2.5');

    // With only the raw record available the cooked row is skipped, never answered with raw rice.
    FoodSubmission::query()->delete();
    $onlyRaw = synImport([synFoods()[0]], [synCuration()[0]]);
    expect(synRow($onlyRaw, 'FOOD-001')['status'])->toBe('skipped')
        ->and(FoodSubmission::count())->toBe(0);
});

it('does not let a longer head noun satisfy a shorter query', function () {
    expect(synRow(synImport(), 'FOOD-009')['fdc_id'])->toBe('900009'); // russet, not sweet potato
});

it('skips ambiguous state variants and unknown foods with a reason instead of guessing', function () {
    $report = synImport();

    expect(synRow($report, 'FOOD-003')['status'])->toBe('skipped')
        ->and(synRow($report, 'FOOD-003')['reason'])->toContain('900006')
        ->and(synRow($report, 'FOOD-019')['status'])->toBe('skipped')
        ->and(synRow($report, 'FOOD-019')['reason'])->toContain('no ');
    expect(FoodSubmission::where('source_id', '900006')->exists())->toBeFalse();
});

it('picks a deterministic variant for same-food varieties and records the alternatives', function () {
    synImport();

    $apple = synSubmission('900004');
    expect($apple->candidate['provenance']['match'])->toMatchArray(['kind' => 'variant'])
        ->and($apple->candidate['provenance']['match']['alternatives'][0]['fdc_id'])->toBe('900005');
    expect(FoodSubmission::where('source_id', '900005')->exists())->toBeFalse();
});

it('maps modern nutrient ids per 100 g, ignores the legacy number and keeps strings', function () {
    synImport();

    $candidate = synSubmission('900001')->candidate;
    expect($candidate['name'])->toBe('Beras putih mentah')
        ->and($candidate['basis'])->toBe('100g')
        ->and($candidate['nutrients'])->toMatchArray([
            'protein_g' => '7.5', 'fat_g' => '1.25', 'carbohydrate_g' => '80.5',
            'sodium_mg' => '0.5', 'sugars_total_g' => null,
        ])
        ->and($candidate['aliases'])->toContain('beras putih mentah');

    // Broccoli carries nutrient id 9999 whose legacy number is "1003"; protein must stay unknown.
    $broccoli = synSubmission('900003')->candidate;
    expect($broccoli['nutrients']['protein_g'])->toBeNull()
        ->and($broccoli['provenance']['nutrients']['protein_g']['qualifier'])->toBe('unknown');
});

it('selects one energy value by precedence 2048, 2047, 1008 and never sums', function () {
    synImport();

    $rice = synSubmission('900001')->candidate;
    expect($rice['nutrients']['energy_kcal'])->toBe('370')
        ->and($rice['nutrients']['energy_method'])->toBe('Atwater specific factors')
        ->and($rice['provenance']['energy']['nutrient_id'])->toBe(2048);

    $general = synImport([synFood(900020, 'Broccoli, raw', [2047 => 31, 1008 => 33, 1003 => 1])], [synCuration()[6]]);
    expect(synRow($general, 'FOOD-016')['status'])->toBe('created');
    $candidate = synSubmission('900020')->candidate;
    expect($candidate['nutrients']['energy_kcal'])->toBe('31')
        ->and($candidate['nutrients']['energy_method'])->toBe('Atwater general factors');

    $legacy = synSubmission('900003')->candidate;
    expect($legacy['nutrients']['energy_kcal'])->toBe('31')
        ->and($legacy['nutrients']['energy_method'])->toBe('Legacy energy')
        ->and($legacy['provenance']['energy']['nutrient_id'])->toBe(1008);
});

it('turns LOQ, missing, negative and unit-mismatched values into null with provenance, keeping exact zero', function () {
    synImport();

    $broccoli = synSubmission('900003')->candidate;
    expect($broccoli['nutrients']['sodium_mg'])->toBeNull()
        ->and($broccoli['provenance']['nutrients']['sodium_mg'])->toMatchArray(['qualifier' => 'less_than', 'reason' => 'loq_reported'])
        ->and($broccoli['provenance']['nutrients']['sodium_mg']['source_amount'])->toBe('0')
        ->and($broccoli['provenance']['nutrients']['sugars_total_g']['qualifier'])->toBe('unknown');

    $carrot = synSubmission('900010')->candidate;
    expect($carrot['nutrients']['protein_g'])->toBeNull()
        ->and($carrot['provenance']['nutrients']['protein_g'])->toMatchArray(['qualifier' => 'unknown', 'reason' => 'negative_amount', 'requires_review' => true])
        ->and($carrot['nutrients']['fat_g'])->toBeNull()
        ->and($carrot['provenance']['nutrients']['fat_g']['reason'])->toBe('unit_mismatch');

    $sweet = synSubmission('900008');
    expect($sweet->candidate['nutrients']['sodium_mg'])->toBe('0')
        ->and($sweet->candidate['provenance']['nutrients']['sodium_mg']['qualifier'])->toBe('exact');
});

it('nulls duplicate nutrient entries that disagree and keeps agreeing duplicates', function () {
    $food = synFood(900030, 'Broccoli, raw', [1003 => 2, 2048 => 30]);
    $food['foodNutrients'][] = synNutrient(1003, 3);
    $food['foodNutrients'][] = synNutrient(1004, 0.5);
    $food['foodNutrients'][] = synNutrient(1004, 0.5);
    synImport([$food], [synCuration()[6]]);

    $candidate = synSubmission('900030')->candidate;
    expect($candidate['nutrients']['protein_g'])->toBeNull()
        ->and($candidate['provenance']['nutrients']['protein_g']['reason'])->toBe('duplicate_unresolvable')
        ->and($candidate['nutrients']['fat_g'])->toBe('0.5');
});

it('stores pending submissions with the source triple and creates no foods', function () {
    $report = synImport();

    $submission = synSubmission('900001');
    expect($submission)->status->toBe('pending')
        ->user_id->toBeNull()
        ->source_provider->toBe('USDA_FDC')
        ->snapshot_version->toBe('9999-01-01')
        ->source_sha256->toBe(str_repeat('ab', 32))
        ->food_id->toBeNull();
    expect($submission->candidate['provenance']['source'])->toMatchArray(['fdc_id' => '900001', 'data_type' => 'Foundation'])
        ->and($report['counts'])->toMatchArray(['matched' => 7, 'skipped' => 2, 'created' => 7])
        ->and($report['source_ids'])->toBe(['900001', '900002', '900003', '900004', '900008', '900009', '900010'])
        ->and(Food::count())->toBe(0)
        ->and(FoodSubmission::count())->toBe(7);
});

it('is idempotent: a second run reports unchanged and a changed value is reported, not written', function () {
    synImport();
    $before = FoodSubmission::orderBy('source_id')->get()->map->only(['source_id', 'candidate', 'updated_at'])->all();

    $again = synImport();
    expect($again['counts'])->toMatchArray(['created' => 0, 'unchanged' => 7, 'changed' => 0])
        ->and(FoodSubmission::count())->toBe(7)
        ->and(FoodSubmission::orderBy('source_id')->get()->map->only(['source_id', 'candidate', 'updated_at'])->all())->toEqual($before);

    $foods = synFoods();
    $foods[0]['foodNutrients'][0]['amount'] = 8.5; // protein of the synthetic raw rice
    $changed = synImport($foods);
    expect($changed['counts'])->toMatchArray(['created' => 0, 'unchanged' => 6, 'changed' => 1])
        ->and(synRow($changed, 'FOOD-002')['status'])->toBe('changed')
        ->and(synSubmission('900001')->candidate['nutrients']['protein_g'])->toBe('7.5');
});

it('respects the row limit by priority', function () {
    $report = app(UsdaFoundationImporter::class)->import(synCuration(), synFoods(), synSnapshot(), 2);

    expect(collect($report['rows'])->pluck('candidate_id')->all())->toBe(['FOOD-001', 'FOOD-002']);
});

it('flags candidates that need a reviewer acknowledgement with machine-readable reasons', function () {
    synImport();

    $reasons = fn (string $id) => synSubmission($id)->candidate;
    expect($reasons('900004'))->requires_review->toBeTrue()->review_reasons->toContain('variant_pick')
        ->and($reasons('900001'))->requires_review->toBeTrue()->review_reasons->toBe(['single_variety_only'])
        ->and($reasons('900003')['review_reasons'])->toContain('energy_legacy_1008')
        ->and($reasons('900003')['provenance']['energy'])->toMatchArray(['requires_review' => true, 'review_reasons' => ['energy_legacy_1008']])
        ->and($reasons('900008')['review_reasons'])->toBe(['single_variety_only', 'exact_zero:sodium_mg'])
        ->and($reasons('900010')['review_reasons'])->toBe(['single_variety_only', 'nutrient_review:protein_g', 'nutrient_review:fat_g']);

    // A plain unique match with modern energy and no zeros needs no acknowledgement.
    synImport([synFood(900040, 'Broccoli, raw', [1003 => 2, 1004 => 0.5, 1005 => 6, 1093 => 30, 2048 => 30])], [synCuration()[6]]);
    expect(synSubmission('900040')->candidate)->requires_review->toBeFalse()->review_reasons->toBe([]);
});

it('refreshes only still-pending rows when asked and never touches decided ones', function () {
    synImport();
    $foods = synFoods();
    $foods[0]['foodNutrients'][0]['amount'] = 8.5; // raw rice protein
    $foods[8]['foodNutrients'][0]['amount'] = 3.5; // russet potato protein
    synSubmission('900009')->update(['status' => 'approved']);
    $before = synSubmission('900009')->only(['candidate', 'revision_counter', 'updated_at']);

    $plain = synImport($foods);
    expect($plain['counts'])->toMatchArray(['changed' => 2, 'refreshed' => 0])
        ->and(synSubmission('900001')->candidate['nutrients']['protein_g'])->toBe('7.5');

    $report = app(UsdaFoundationImporter::class)->import(synCuration(), $foods, synSnapshot(), 20, true);

    expect($report['counts'])->toMatchArray(['refreshed' => 1, 'changed' => 1, 'unchanged' => 5])
        ->and(synRow($report, 'FOOD-002')['status'])->toBe('refreshed')
        ->and(synRow($report, 'FOOD-009')['status'])->toBe('changed')
        ->and(synSubmission('900001')->candidate['nutrients']['protein_g'])->toBe('8.5')
        ->and(synSubmission('900001')->revision_counter)->toBe(2)
        ->and(synSubmission('900009')->only(['candidate', 'revision_counter', 'updated_at']))->toEqual($before)
        ->and(synSubmission('900009')->status)->toBe('approved')
        ->and(Food::count())->toBe(0);
});
