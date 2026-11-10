<?php

use App\Models\ChatTurn;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/ChatApiSupport.php';

beforeEach(function () {
    chatSetUp();
    chatFake();
});

test('database mode answers from the catalog with BCMath numbers, a citation and zero provider calls', function () {
    $food = cfFood(['name' => 'Tahu contoh', 'protein_g' => '10.3333', 'current_revision' => 3]);
    [$user, $session] = chatActor();

    $json = chatOk(chatAsk($user, $session, 'Berapa protein 50 gram tahu contoh?'));

    expect($json['mode'])->toBe('database')->and($json['retrieval_status'])->toBe('found')
        ->and($json['sequence'])->toBe(1)->and($json['session_id'])->toBe($session->id)
        ->and($json['client_turn_id'])->toBe(ctKey(1))
        ->and($json['claims'])->toHaveCount(1)
        ->and($json['claims'][0]['derived_value'])->toBe(5.16665)
        ->and($json['claims'][0]['canonical_value'])->toBe(10.3333)
        ->and($json['claims'][0]['requested_amount'])->toBe(50)
        ->and($json['claims'][0]['revision'])->toBe(3)
        ->and($json['answer'])->toContain('50 g mengandung 5,17 g protein')
        ->and($json['citations'][0]['food_id'])->toBe($food->id)->and($json['citations'][0]['revision'])->toBe(3)
        ->and($json['general_knowledge_notice'])->toBeNull();
    Http::assertNothingSent();
});

test('without an amount the default 100 g is stated', function () {
    cfFood(['name' => 'Tahu contoh', 'protein_g' => '10']);
    [$user, $session] = chatActor();

    $json = chatOk(chatAsk($user, $session, 'Berapa protein tahu contoh?'));

    expect($json['answer'])->toContain('100 g')->and($json['claims'][0]['requested_amount'])->toBe(100)
        ->and($json['claims'][0]['derived_value'])->toBe(10)
        ->and($json['warnings'][0])->toContain('100 g');
});

test('the amount may come from the text or from the body, and the body wins over a household word', function () {
    $food = cfFood(['name' => 'Tahu contoh', 'protein_g' => '10']);
    [$user, $session] = chatActor();

    $fromText = chatOk(chatAsk($user, $session, 'protein 25 g tahu contoh', 1));
    $fromBody = chatOk(chatAsk($user, $session, 'Berapa protein tahu contoh?', 2, ['amount_g' => 40]));
    $bodyAndFood = chatOk(chatAsk($user, $session, 'Berapa protein?', 3, ['amount_g' => '12.5', 'food_id' => $food->id]));
    $household = chatOk(chatAsk($user, $session, 'Berapa protein semangkuk tahu contoh?', 4, ['amount_g' => 200]));

    expect($fromText['claims'][0]['derived_value'])->toBe(2.5)
        ->and($fromBody['claims'][0]['derived_value'])->toBe(4)
        ->and($bodyAndFood['claims'][0]['derived_value'])->toBe(1.25)
        ->and($household['mode'])->toBe('database')->and($household['claims'][0]['derived_value'])->toBe(20);
    Http::assertNothingSent();
});

test('a text amount that differs from the body amount is AMOUNT_CONFLICT and stored for the key', function () {
    cfFood(['name' => 'Tahu contoh']);
    [$user, $session] = chatActor();

    chatError(chatAsk($user, $session, 'protein 50 gram tahu contoh', 1, ['amount_g' => 60]), 422, 'AMOUNT_CONFLICT');

    expect(ChatTurn::query()->first()->status)->toBe('failed')->and(ChatTurn::query()->first()->error_code)->toBe('AMOUNT_CONFLICT');
    chatError(chatAsk($user, $session, 'protein 50 gram tahu contoh', 1, ['amount_g' => 60]), 422, 'AMOUNT_CONFLICT');
    Http::assertNothingSent();
});

test('a body food_id that differs from the food named in the text is AMOUNT_CONFLICT', function () {
    cfFood(['name' => 'Tahu contoh']);
    $other = cfFood(['name' => 'Bayam contoh']);
    [$user, $session] = chatActor();

    chatError(chatAsk($user, $session, 'Berapa protein tahu contoh?', 1, ['food_id' => $other->id]), 422, 'AMOUNT_CONFLICT');

    $same = chatOk(chatAsk($user, $session, 'Berapa protein bayam contoh?', 2, ['food_id' => $other->id]));
    expect($same['citations'][0]['food_id'])->toBe($other->id);
    Http::assertNothingSent();
});

test('a body food_id disambiguates variants named in the text', function () {
    cfFood(['name' => 'Nasi putih', 'aliases' => ['nasi', 'nasi putih']]);
    $brown = cfFood(['name' => 'Nasi merah', 'aliases' => ['nasi', 'nasi merah'], 'energy_kcal' => '110']);
    [$user, $session] = chatActor();

    $json = chatOk(chatAsk($user, $session, 'Berapa kalori nasi?', 1, ['food_id' => $brown->id]));

    expect($json['mode'])->toBe('database')->and($json['citations'][0]['food_name'])->toBe('Nasi merah');
});

test('an ambiguous name asks for clarification and picks no variant', function () {
    cfFood(['name' => 'Nasi putih', 'aliases' => ['nasi', 'nasi putih']]);
    cfFood(['name' => 'Nasi merah', 'aliases' => ['nasi', 'nasi merah']]);
    [$user, $session] = chatActor();

    $json = chatOk(chatAsk($user, $session, 'Berapa kalori nasi?'));

    expect($json['mode'])->toBe('clarification')->and($json['retrieval_status'])->toBe('ambiguous')
        ->and($json['claims'])->toBe([])->and($json['citations'])->toBe([])
        ->and($json['answer'])->toContain('Nasi putih')->and($json['answer'])->toContain('Nasi merah');
    Http::assertNothingSent();
});

test('a household amount asks for grams and remembers the named food for the next turn', function () {
    $food = cfFood(['name' => 'Tahu contoh', 'protein_g' => '10']);
    [$user, $session] = chatActor();

    $ask = chatOk(chatAsk($user, $session, 'Berapa protein semangkuk tahu contoh?', 1));
    $next = chatOk(chatAsk($user, $session, 'kalau 100 gram?', 2));

    expect($ask['mode'])->toBe('clarification')->and($ask['answer'])->toContain('gram')
        ->and(ChatTurn::query()->where('sequence', 1)->first()->food_refs)->toBe([['food_id' => $food->id, 'revision' => 3]])
        ->and($next['mode'])->toBe('database')->and($next['citations'][0]['food_id'])->toBe($food->id);
    Http::assertNothingSent();
});

test('an unknown product is no_match, invents no numbers and calls no model', function () {
    cfFood(['name' => 'Tahu contoh']);
    [$user, $session] = chatActor();

    $json = chatOk(chatAsk($user, $session, 'Gula produk merek yang tidak ada?'));

    expect($json['mode'])->toBe('general')->and($json['retrieval_status'])->toBe('no_match')
        ->and($json['claims'])->toBe([])->and($json['citations'])->toBe([])
        ->and($json['answer'])->toContain('memindai label')->and($json['answer'])->not->toMatch('/\d/')
        ->and($json['general_knowledge_notice'])->toBeString();
    Http::assertNothingSent();
});

test('a lookup without any food and no earlier food asks which food', function () {
    cfFood(['name' => 'Tahu contoh']);
    [$user, $session] = chatActor();

    $json = chatOk(chatAsk($user, $session, 'kalau 50 gram?'));

    expect($json['mode'])->toBe('clarification')->and($json['claims'])->toBe([]);
});

test('a revoked body food_id is no_match and never a numeric answer', function () {
    $food = cfFood(['name' => 'Tahu contoh', 'status' => 'revoked']);
    [$user, $session] = chatActor();

    $json = chatOk(chatAsk($user, $session, 'Berapa protein?', 1, ['food_id' => $food->id]));

    expect($json['retrieval_status'])->toBe('no_match')->and($json['claims'])->toBe([]);
});

test('a clinical question is answered with a referral, no numbers and no model call', function () {
    [$user, $session] = chatActor();

    $json = chatOk(chatAsk($user, $session, 'Berapa dosis insulin dari makanan ini?'));

    expect($json['mode'])->toBe('referral')->and($json['claims'])->toBe([])->and($json['answer'])->toContain('tenaga kesehatan');
    Http::assertNothingSent();
});

test('invalid bodies are 422 INVALID_INPUT and never reach the gate', function (array $body) {
    [$user, $session] = chatActor();

    chatError(chatSend($user, $session, $body), 422, 'INVALID_INPUT');

    expect(ChatTurn::query()->count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'no message' => [['client_turn_id' => '40000000-0000-4000-8000-000000000001']],
    'blank message' => [['message' => "  \n ", 'client_turn_id' => '40000000-0000-4000-8000-000000000001']],
    'unicode blank' => [['message' => "\u{00A0}\u{3000} \n", 'client_turn_id' => '40000000-0000-4000-8000-000000000001']],
    'long message' => [['message' => str_repeat('a', 2001), 'client_turn_id' => '40000000-0000-4000-8000-000000000001']],
    'no key' => [['message' => 'halo']],
    'bad key' => [['message' => 'halo', 'client_turn_id' => 'abc']],
    'bad food id' => [['message' => 'halo', 'client_turn_id' => '40000000-0000-4000-8000-000000000001', 'food_id' => 'x']],
    'zero amount' => [['message' => 'halo', 'client_turn_id' => '40000000-0000-4000-8000-000000000001', 'amount_g' => 0]],
    'negative amount' => [['message' => 'halo', 'client_turn_id' => '40000000-0000-4000-8000-000000000001', 'amount_g' => -5]],
    'huge amount' => [['message' => 'halo', 'client_turn_id' => '40000000-0000-4000-8000-000000000001', 'amount_g' => 2001]],
    'text amount' => [['message' => 'halo', 'client_turn_id' => '40000000-0000-4000-8000-000000000001', 'amount_g' => 'banyak']],
    'three decimals' => [['message' => 'halo', 'client_turn_id' => '40000000-0000-4000-8000-000000000001', 'amount_g' => 1.234]],
    'array message' => [['message' => ['a'], 'client_turn_id' => '40000000-0000-4000-8000-000000000001']],
]);

test('boundary values are accepted: 2000 characters, 2000 g, unicode counted as characters', function () {
    cfFood(['name' => 'Tahu contoh', 'protein_g' => '10']);
    [$user, $session] = chatActor();

    $max = chatOk(chatAsk($user, $session, 'Berapa protein tahu contoh?', 1, ['amount_g' => 2000]));
    $long = chatSend($user, $session, chatBody(str_repeat('é', 2000), 2));

    expect($max['claims'][0]['derived_value'])->toBe(200);
    $long->assertOk();
});

test('extra body fields are ignored: the owner and ids come from auth and the session route', function () {
    cfFood(['name' => 'Tahu contoh']);
    [$user, $session] = chatActor();
    $other = ctUser();

    $json = chatOk(chatAsk($user, $session, 'Berapa protein tahu contoh?', 1, ['user_id' => $other->id, 'session_id' => 'x', 'sequence' => 99, 'mode' => 'general']));

    expect($json['sequence'])->toBe(1)->and($json['session_id'])->toBe($session->id)->and($json['mode'])->toBe('database');
});

test('guests get 401, users without the permission 403, foreign sessions and admins 404', function () {
    [$owner, $session] = chatActor();
    $url = "/ai/v1/chat/sessions/{$session->id}/turns";
    $body = chatBody('halo');

    $this->postJson($url, $body)->assertUnauthorized();
    chatSend(ctUser(''), $session, $body)->assertForbidden();
    chatError(chatSend(ctUser('family_head'), $session, $body), 404, 'NOT_FOUND');
    chatError(chatSend(ctUser('admin'), $session, $body), 404, 'NOT_FOUND');
    chatError(chatSend($owner, ctSession($owner, ['state' => 'deleted']), $body), 404, 'NOT_FOUND');

    expect(ChatTurn::query()->count())->toBe(0);
    Http::assertNothingSent();
});
