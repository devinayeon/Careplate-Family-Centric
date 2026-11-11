<?php

use App\Services\Ai\Foods\FoodMatch;
use App\Services\Ai\Foods\FoodSearch;

require_once __DIR__.'/ChatFoodSupport.php';
require_once __DIR__.'/FoodSubmissionSupport.php';

// MySQL's utf8mb4_unicode_ci compares case-, accent- and trailing-space-insensitively; SQLite compares bytes.
// The decisions below must be identical on both: SQL is only a prefilter, PHP decides (exact tokens). SYNTHETIC data.

function collSearch(): FoodSearch
{
    return app(FoodSearch::class);
}

/** Names returned by GET /ai/v1/foods?q=$q for a nutritionist. */
function collList(string $q): array
{
    return array_column(test()->actingAs(fsUser('nutritionist'))->getJson('/ai/v1/foods?q='.rawurlencode($q))->assertOk()->json('data'), 'name');
}

test('an accented query never resolves to an unaccented alias, and the other way round', function () {
    cfFood(['name' => 'Tahu contoh', 'aliases' => ['tahu contoh']]);
    cfFood(['name' => 'Café susu', 'aliases' => ['café susu']]);

    expect(collSearch()->resolve(['táhu contoh'])->state)->toBe(FoodMatch::NO_MATCH)
        ->and(collSearch()->resolve(['cafe susu'])->state)->toBe(FoodMatch::NO_MATCH)
        ->and(collSearch()->resolve(['café susu'])->state)->toBe(FoodMatch::FOUND)
        ->and(collSearch()->resolve(['tahu contoh'])->candidates[0]->name)->toBe('Tahu contoh');
});

test('case and surrounding or repeated spaces are folded on purpose, on both engines', function () {
    $tahu = cfFood(['name' => 'Tahu contoh', 'aliases' => ['tahu contoh']]);

    foreach (['TAHU CONTOH', 'Tahu Contoh', 'tahu contoh ', "  tahu\u{00A0}contoh\t", 'tahu   contoh'] as $phrase) {
        $match = collSearch()->resolve([$phrase]);
        expect($match->state)->toBe(FoodMatch::FOUND)->and($match->candidates[0]->is($tahu))->toBeTrue();
    }
});

test('foods differing only by accent stay two separate foods and an exact alias still wins', function () {
    $plain = cfFood(['name' => 'Tempe plain', 'aliases' => ['tempe goreng']]);
    $accent = cfFood(['name' => 'Tempé plain', 'aliases' => ['tempé goreng']]);

    $a = collSearch()->resolve(['tempe goreng']);
    $b = collSearch()->resolve(['tempé goreng']);

    expect($a->state)->toBe(FoodMatch::FOUND)->and($a->candidates[0]->is($plain))->toBeTrue()
        ->and($b->state)->toBe(FoodMatch::FOUND)->and($b->candidates[0]->is($accent))->toBeTrue();
});

test('GET /foods?q= returns only foods whose text really contains the folded query', function () {
    cfFood(['name' => 'Tahu contoh', 'aliases' => ['tahu contoh']]);
    cfFood(['name' => 'Café susu', 'aliases' => ['café susu']]);
    expect(collList('táhu'))->toBe([])
        ->and(collList('cafe'))->toBe([])
        ->and(collList('CAFÉ'))->toBe(['Café susu'])
        ->and(collList('tahu '))->toBe(['Tahu contoh'])
        ->and(collList('TAHU'))->toBe(['Tahu contoh'])
        ->and(collList('tahú'))->toBe([]);
});

test('list() applies the PHP check after the SQL prefilter, so accent look-alikes never fill the limit', function () {
    foreach (range(1, 6) as $i) {
        cfFood(['name' => "Tahú {$i}", 'aliases' => ["tahú {$i}"], 'source_id' => "acc-{$i}"]);
    }
    $real = cfFood(['name' => 'Tahu zzz', 'aliases' => ['tahu zzz'], 'source_id' => 'plain']);

    $found = collSearch()->list('tahu', 5);

    expect($found)->toHaveCount(1)->and($found[0]->is($real))->toBeTrue();
});
