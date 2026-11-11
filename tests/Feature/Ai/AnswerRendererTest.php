<?php

use App\Services\Ai\Chat\AnswerRenderer;
use App\Services\Ai\Chat\Intent;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/ChatFoodSupport.php';

function renderer(): AnswerRenderer
{
    return app(AnswerRenderer::class);
}

/** The synthetic Tahu Contoh of examples/chat-database.json (protein 10 g / 100 g, revision 3). */
function tahu(array $attrs = [])
{
    return cfFood(array_merge([
        'name' => 'Tahu Contoh (sintetis)', 'source_provider' => 'FIXTURE', 'source_id' => 'demo-'.random_int(1, 999999999),
        'source_description' => 'Fixture demo, bukan nilai gizi pangan nyata',
        'protein_g' => '10', 'current_revision' => 3, 'verified_at' => '2026-11-06 02:00:00',
    ], $attrs));
}

test('a database answer follows the contract example: 50 g of 10 g/100 g protein is 5 g', function () {
    $food = tahu();

    $r = renderer()->database($food, ['protein_g'], '50');

    cfAssertChatShape($r);
    expect($r['mode'])->toBe('database')
        ->and($r['retrieval_status'])->toBe('found')
        ->and($r['general_knowledge_notice'])->toBeNull()
        ->and($r['claims'])->toBe([[
            'food_id' => $food->id, 'revision' => 3, 'nutrient' => 'protein_g', 'canonical_value' => 10,
            'unit' => 'g', 'canonical_basis' => '100g', 'requested_amount' => 50, 'derived_value' => 5,
            'source_type' => 'database', 'citation_id' => 'food-1',
        ]])
        ->and($r['citations'])->toBe([[
            'id' => 'food-1', 'food_id' => $food->id, 'revision' => 3, 'food_name' => 'Tahu Contoh (sintetis)',
            'basis' => '100g', 'source_reference' => $r['citations'][0]['source_reference'],
            'verified_at' => '2026-11-06T02:00:00Z',
        ]])
        ->and($r['answer'])->toContain('Tahu Contoh (sintetis)', '50 g', '5 g protein', 'revisi 3', 'basis 100 g');
});

test('without an amount the 100 g basis is stated explicitly', function () {
    $r = renderer()->database(tahu(), ['protein_g'], null);

    cfAssertChatShape($r);
    expect($r['claims'][0]['requested_amount'])->toBe(100)
        ->and($r['claims'][0]['derived_value'])->toBe(10)
        ->and($r['answer'])->toContain('100 g', 'tidak disebutkan')
        ->and($r['warnings'])->toContain('Jumlah tidak disebutkan, sehingga dipakai basis data 100 g.');
});

test('derivation is BCMath canonical x amount / 100: text rounds to 2 decimals, claims and comparison keep precision', function () {
    $food = tahu(['protein_g' => '7.04', 'fat_g' => '0.0140']);

    $r = renderer()->database($food, ['protein_g', 'fat_g'], '33');
    $byNutrient = collect($r['claims'])->keyBy('nutrient');

    expect($byNutrient['protein_g']['canonical_value'])->toBe(7.04)
        ->and($byNutrient['protein_g']['derived_value'])->toBe(2.3232)
        ->and($byNutrient['fat_g']['derived_value'])->toBe(0.00462)
        ->and($r['answer'])->toContain('2,32 g protein', 'kurang dari 0,01 g lemak')->not->toContain('2.32')
        ->and(AnswerRenderer::derive('7.0400', '33'))->toBe('2.32320000')
        ->and(bccomp(AnswerRenderer::derive('0.0140', '50'), '0.007', 8))->toBe(0)
        ->and(renderer()->database($food, ['fat_g'], '50')['claims'][0]['derived_value'])->toBe(0.007)
        ->and(renderer()->database(tahu(['fat_g' => '1.005']), ['fat_g'], '100')['answer'])->toContain('1,01 g lemak');
});

test('unknown nutrient makes no numeric claim but keeps the citation and says unknown, not zero', function () {
    $food = tahu(['sugars_total_g' => null]);

    $r = renderer()->database($food, ['sugars_total_g'], '50');

    cfAssertChatShape($r);
    expect($r['mode'])->toBe('database')
        ->and($r['retrieval_status'])->toBe('found')
        ->and($r['claims'])->toBe([])
        ->and($r['citations'])->toHaveCount(1)
        ->and($r['answer'])->toContain('belum diketahui')->not->toMatch('/\b0 g\b/');
});

test('known and unknown nutrients mix: the known one is claimed, the unknown one is declared unknown', function () {
    $r = renderer()->database(tahu(['sugars_total_g' => null]), ['protein_g', 'sugars_total_g'], '100');

    cfAssertChatShape($r);
    expect(collect($r['claims'])->pluck('nutrient')->all())->toBe(['protein_g'])
        ->and($r['answer'])->toContain('10 g protein', 'gula total belum diketahui');
});

test('a stored zero is a real zero claim, distinct from null', function () {
    $r = renderer()->database(tahu(['fat_g' => '0']), ['fat_g'], '50');

    expect($r['claims'][0]['canonical_value'])->toBe(0)->and($r['claims'][0]['derived_value'])->toBe(0);
});

test('no requested nutrient answers all six in contract order with proper units', function () {
    $r = renderer()->database(tahu(), [], '100');

    cfAssertChatShape($r);
    expect(collect($r['claims'])->map(fn ($c) => $c['nutrient'].':'.$c['unit'])->all())
        ->toBe(['energy_kcal:kcal', 'fat_g:g', 'sugars_total_g:g', 'protein_g:g', 'carbohydrate_g:g', 'sodium_mg:mg']);
});

test('the citation follows the model row: current revision, ISO UTC time, source reference without a url or nutritionist claim', function () {
    $food = cfFood([
        'name' => 'Beras putih mentah', 'source_provider' => 'USDA_FDC', 'source_id' => '2512381',
        'snapshot_version' => '2026-10-29', 'source_description' => 'Rice, white, long grain, unenriched, raw',
        'source_url' => 'https://fdc.nal.usda.gov/food-details/2512381/nutrients', 'current_revision' => 7,
        'verified_at' => '2026-11-08 07:55:41',
    ]);

    $citation = renderer()->database($food, ['protein_g'], null)['citations'][0];

    expect($citation['revision'])->toBe(7)
        ->and($citation['verified_at'])->toBe('2026-11-08T07:55:41Z')
        ->and($citation['source_reference'])->toContain('USDA FoodData Central', '2512381', '2026-10-29', 'Ditinjau sumber')
        ->and($citation['source_reference'])->not->toContain('http')->not->toContain('ahli gizi');
});

test('a food revised after it was loaded is rendered from the row given, so a refetched row shows the new revision', function () {
    $food = tahu();
    $food->forceFill(['current_revision' => 4, 'protein_g' => '11'])->save();

    $r = renderer()->database($food->fresh(), ['protein_g'], '100');

    expect($r['claims'][0]['revision'])->toBe(4)->and($r['claims'][0]['canonical_value'])->toBe(11)
        ->and($r['citations'][0]['revision'])->toBe(4);
});

test('no rendered text claims nutritionist verification', function () {
    $texts = [
        renderer()->database(tahu(), [], null)['answer'],
        renderer()->ambiguous([tahu(), tahu(['name' => 'Tahu lain'])])['answer'],
        renderer()->noMatch()['answer'],
        renderer()->referral()['answer'],
        renderer()->clarification(Intent::REASON_HOUSEHOLD)['answer'],
    ];

    foreach ($texts as $text) {
        expect(mb_strtolower($text))->not->toContain('ahli gizi')->not->toContain('nutritionist')->not->toContain('terverifikasi oleh');
    }
    expect($texts[0])->toContain('ditinjau sumber');
});

test('salt note: sodium is not salt', function () {
    $r = renderer()->database(tahu(), ['sodium_mg'], '100', [Intent::NOTE_SALT_IS_NOT_SODIUM]);

    expect($r['warnings'])->toContain('Data memuat natrium (mg), bukan garam (NaCl); keduanya tidak sama.');
});

test('rendering a database answer never needs the model', function () {
    Http::fake();
    Http::preventStrayRequests();

    renderer()->database(tahu(), ['protein_g'], '50');

    Http::assertNothingSent();
});

test('ambiguity asks which food, lists up to five names and offers no numbers', function () {
    $foods = [];
    foreach (range(1, 7) as $i) {
        $foods[] = cfFood(['name' => "Nasi varian $i"]);
    }

    $r = renderer()->ambiguous($foods);

    cfAssertChatShape($r);
    expect($r['mode'])->toBe('clarification')
        ->and($r['retrieval_status'])->toBe('ambiguous')
        ->and($r['answer'])->toContain('Nasi varian 1', 'Nasi varian 5')->not->toContain('Nasi varian 6')
        ->and($r['answer'])->not->toMatch('/\d+(,\d+)? (g|kkal|kcal|mg)\b/');
});

test('clarifications for household, volume, salt, multiple amounts and long text each ask one specific thing', function (string $reason, string $needle) {
    $r = renderer()->clarification($reason);

    cfAssertChatShape($r);
    expect($r['mode'])->toBe('clarification')->and($r['retrieval_status'])->toBe('ambiguous')->and($r['answer'])->toContain($needle);
})->with([
    [Intent::REASON_HOUSEHOLD, 'gram'],
    [Intent::REASON_VOLUME, 'gram'],
    [Intent::REASON_SALT, 'natrium'],
    [Intent::REASON_MULTIPLE_AMOUNTS, 'satu jumlah'],
    [Intent::REASON_TOO_LONG, 'satu makanan'],
]);

test('a household clarification names the food when one is known', function () {
    expect(renderer()->clarification(Intent::REASON_HOUSEHOLD, tahu())['answer'])->toContain('Tahu Contoh (sintetis)');
});

test('no_match says the product is not in the verified database, suggests a scan and invents no values', function () {
    $r = renderer()->noMatch();

    cfAssertChatShape($r);
    expect($r['mode'])->toBe('general')
        ->and($r['retrieval_status'])->toBe('no_match')
        ->and($r['answer'])->toContain('belum ada dalam database', 'memindai label')
        ->and($r['answer'])->not->toMatch('/\d/')
        ->and($r['general_knowledge_notice'])->toBe(AnswerRenderer::GENERAL_NOTICE);
});

test('referral is a specific hand-off to a health professional with no dose or advice numbers', function () {
    $r = renderer()->referral();

    cfAssertChatShape($r);
    expect($r['mode'])->toBe('referral')
        ->and($r['retrieval_status'])->toBe('not_needed')
        ->and($r['answer'])->toContain('tenaga kesehatan')
        ->and($r['answer'])->not->toMatch('/\d/');
});
