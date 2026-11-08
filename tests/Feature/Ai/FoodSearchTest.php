<?php

use App\Exceptions\Ai\AiException;
use App\Models\Food;
use App\Services\Ai\Foods\FoodMatch;
use App\Services\Ai\Foods\FoodSearch;

require_once __DIR__.'/ChatFoodSupport.php';
require_once __DIR__.'/DbPortabilitySupport.php';

function search(): FoodSearch
{
    return app(FoodSearch::class);
}

test('find returns a verified food by id and nothing else', function () {
    $verified = cfFood(['name' => 'Tahu contoh']);
    $revoked = cfFood(['name' => 'Tempe lama', 'status' => 'revoked', 'search_text' => null]);

    expect(search()->find($verified->id)?->is($verified))->toBeTrue()
        ->and(search()->find($revoked->id))->toBeNull()
        ->and(search()->find('10000000-0000-4000-8000-000000000099'))->toBeNull()
        ->and(search()->find('not-a-uuid'))->toBeNull()
        ->and(search()->find("' OR 1=1 --"))->toBeNull();
});

test('find reads numbers through the model as decimal strings', function () {
    $food = cfFood(['protein_g' => '7.04']);

    expect(search()->find($food->id)->protein_g)->toBe('7.0400');
});

test('one exact alias is found and the sentence is not used as a LIKE pattern', function () {
    $tahu = cfFood(['name' => 'Tahu contoh', 'aliases' => ['tahu contoh', 'tofu contoh']]);
    cfFood(['name' => 'Tempe contoh']);

    $match = search()->resolve(['tahu contoh']);

    expect($match->state)->toBe(FoodMatch::FOUND)
        ->and($match->candidates)->toHaveCount(1)
        ->and($match->candidates[0]->is($tahu))->toBeTrue()
        ->and(search()->resolve(['tofu contoh'])->state)->toBe(FoodMatch::FOUND);
});

test('a partial alias with several variants is ambiguous and ordered by name then id', function () {
    cfFood(['name' => 'Nasi putih']);
    cfFood(['name' => 'Nasi goreng']);
    cfFood(['name' => 'Roti tawar']);

    $match = search()->resolve(['nasi']);

    expect($match->state)->toBe(FoodMatch::AMBIGUOUS)
        ->and(cfNames($match->candidates))->toBe(['Nasi goreng', 'Nasi putih']);
});

test('a longer or different phrase than any alias is no_match, never the closest food', function () {
    cfFood(['name' => 'Kentang mentah']);

    expect(search()->resolve(['kentang goreng'])->state)->toBe(FoodMatch::NO_MATCH)
        ->and(search()->resolve(['produk merek'])->state)->toBe(FoodMatch::NO_MATCH)
        ->and(search()->resolve([])->state)->toBe(FoodMatch::NO_MATCH)
        ->and(search()->resolve(['kentang', 'direbus'])->state)->toBe(FoodMatch::NO_MATCH)
        ->and(search()->resolve(['kentang'])->state)->toBe(FoodMatch::FOUND);
});

test('english words of the source description are not an alias whitelist', function () {
    cfFood(['name' => 'Beras putih mentah', 'source_description' => 'Rice, white, long grain, raw']);

    expect(search()->resolve(['raw'])->state)->toBe(FoodMatch::NO_MATCH)
        ->and(search()->resolve(['rice'])->state)->toBe(FoodMatch::NO_MATCH);
});

test('two different foods named in one message are ambiguous', function () {
    cfFood(['name' => 'Apel']);
    cfFood(['name' => 'Wortel']);

    $match = search()->resolve(['apel', 'wortel']);

    expect($match->state)->toBe(FoodMatch::AMBIGUOUS)->and(cfNames($match->candidates))->toBe(['Apel', 'Wortel']);
});

test('at most five candidates are returned', function () {
    foreach (range(1, 8) as $i) {
        cfFood(['name' => "Nasi varian $i"]);
    }

    $match = search()->resolve(['nasi']);

    expect($match->state)->toBe(FoodMatch::AMBIGUOUS)
        ->and($match->candidates)->toHaveCount(5)
        ->and(cfNames($match->candidates)[0])->toBe('Nasi varian 1');
});

test('a common word shared by more than 200 foods does not push the intended food out of the prefilter', function () {
    foreach (range(1, 250) as $i) {
        cfFood(['name' => sprintf('Aneka sayur mentah %03d', $i)]);
    }
    $wortel = cfFood(['name' => 'Wortel mentah']);

    $match = search()->resolve(['wortel mentah']);

    expect($match->state)->toBe(FoodMatch::FOUND)->and($match->candidates[0]->is($wortel))->toBeTrue();
});

test('a phrase that would need more than 200 rows is ambiguous, never found or no_match', function () {
    foreach (range(1, 205) as $i) {
        cfFood(['name' => sprintf('Nasi varian %03d', $i)]);
    }

    $broad = search()->resolve(['nasi']);

    expect($broad->state)->toBe(FoodMatch::AMBIGUOUS)
        ->and($broad->candidates)->toHaveCount(5)
        ->and(cfNames($broad->candidates)[0])->toBe('Nasi varian 001');
});

test('exactly 200 matching rows is not treated as truncated', function () {
    foreach (range(1, 200) as $i) {
        cfFood(['name' => sprintf('Beras jenis %03d', $i)]);
    }

    expect(search()->resolve(['beras'])->state)->toBe(FoodMatch::AMBIGUOUS)
        ->and(search()->resolve(['beras jenis 007'])->state)->toBe(FoodMatch::FOUND);
});

test('every token of a run must be present: a run with one unknown word matches nothing', function () {
    cfFood(['name' => 'Wortel mentah']);
    cfFood(['name' => 'Apel mentah']);

    expect(search()->resolve(['wortel apel'])->state)->toBe(FoodMatch::NO_MATCH);
});

test('pending and revoked foods are never returned by any entry point', function () {
    cfFood(['name' => 'Tahu contoh']);
    cfFood(['name' => 'Tahu pending', 'status' => 'pending']);
    cfFood(['name' => 'Tahu cabut', 'status' => 'revoked', 'search_text' => null]);

    expect(cfNames(search()->list('tahu', 5)))->toBe(['Tahu contoh'])
        ->and(cfNames(search()->list('', 5)))->toBe(['Tahu contoh'])
        ->and(search()->resolve(['tahu'])->state)->toBe(FoodMatch::FOUND)
        ->and(cfNames(search()->resolve(['tahu'])->candidates))->toBe(['Tahu contoh'])
        ->and(search()->resolve(['tahu cabut'])->state)->toBe(FoodMatch::NO_MATCH);
});

test('case and unicode forms fold to the same stored text', function () {
    $food = cfFood(['name' => "Cr\u{e8}me br\u{fb}l\u{e9}e", 'aliases' => ["cr\u{e8}me br\u{fb}l\u{e9}e"]]);
    $decomposed = "CRE\u{300}ME BRU\u{302}LE\u{301}E";
    $fullwidth = "\u{ff34}\u{ff21}\u{ff28}\u{ff35}";
    cfFood(['name' => 'Tahu']);

    expect(search()->resolve([$decomposed])->candidates[0]->is($food))->toBeTrue()
        ->and(search()->resolve([$fullwidth])->state)->toBe(FoodMatch::FOUND)
        ->and(cfNames(search()->list('TAHU', 5)))->toBe(['Tahu'])
        ->and(cfNames(search()->list($decomposed, 5)))->toBe(["Cr\u{e8}me br\u{fb}l\u{e9}e"]);
});

test('like wildcards in user text are escaped and match nothing extra', function () {
    cfFood(['name' => 'Tahu contoh']);
    cfFood(['name' => 'Tempe']);

    expect(FoodSearch::escapeLike('50%_!x'))->toBe('50!%!_!!x')
        ->and(search()->list('%', 5))->toHaveCount(0)
        ->and(search()->list('_', 5))->toHaveCount(0)
        ->and(search()->list('t_hu', 5))->toHaveCount(0)
        ->and(search()->list('t%u', 5))->toHaveCount(0)
        ->and(search()->list('\\', 5))->toHaveCount(0)
        ->and(search()->resolve(['%'])->state)->toBe(FoodMatch::NO_MATCH)
        ->and(search()->resolve(['t_hu'])->state)->toBe(FoodMatch::NO_MATCH);
});

test('sql injection text is only ever a bound parameter', function () {
    cfFood(['name' => 'Tahu contoh']);

    expect(search()->list("'; DROP TABLE foods; --", 5))->toHaveCount(0)
        ->and(search()->resolve(["tahu' OR '1'='1"])->state)->toBe(FoodMatch::NO_MATCH)
        ->and(Food::count())->toBe(1);
});

test('list caps limit at five, orders by name then id and rejects a query over 200 characters', function () {
    foreach (range(1, 7) as $i) {
        cfFood(['name' => "Buah $i"]);
    }

    expect(search()->list('', 99))->toHaveCount(5)
        ->and(cfNames(search()->list('', 2)))->toBe(['Buah 1', 'Buah 2'])
        ->and(fn () => search()->list(str_repeat('a', 201), 5))->toThrow(AiException::class);
});

test('a database failure is KNOWLEDGE_UNAVAILABLE 503, never an empty result', function () {
    cfFood(['name' => 'Tahu contoh']);
    dbBreakTable('foods');

    foreach ([
        fn () => search()->find('10000000-0000-4000-8000-000000000001'),
        fn () => search()->resolve(['tahu contoh']),
        fn () => search()->list('tahu', 5),
        fn () => search()->list('', 5),
    ] as $call) {
        $e = cfThrown($call);
        expect($e->errorCode)->toBe('KNOWLEDGE_UNAVAILABLE')
            ->and($e->status)->toBe(503)
            ->and($e->retryable)->toBeTrue()
            ->and($e->getMessage())->not->toContain('foods');
    }
});
