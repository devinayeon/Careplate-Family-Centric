<?php

use App\Models\ChatSession;
use App\Services\Ai\Chat\SessionEraser;
use Database\Seeders\AiPermissionSeeder;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/ChatTurnSupport.php';
require_once __DIR__.'/DbPortabilitySupport.php';

beforeEach(function () {
    test()->seed(AiPermissionSeeder::class);
    test()->travelTo('2026-11-08 10:00:00');
    Http::fake();
});

afterEach(fn () => Http::assertNothingSent());

test('finalize stores the response, citations, refs and mode, clears the slot and sets last_food_id', function () {
    $food = cfFood(['current_revision' => 3]);
    $session = ctSession();
    $turn = ctReserve($session)->turn;
    test()->travelTo('2026-11-08 10:00:20');

    $ok = ctGate()->finalize($turn, ctResponse(), [ctCitation($food)], [ctFoodRef($food)]);
    $t = ctFresh($turn);
    $s = ctFresh($session);

    expect($ok)->toBeTrue()
        ->and($t->status)->toBe('completed')
        ->and(dbCanonical($t->response))->toBe(dbCanonical(ctResponse()))
        ->and(dbCanonical($t->citations))->toBe(dbCanonical([ctCitation($food)]))
        ->and(dbCanonical($t->food_refs))->toBe(dbCanonical([ctFoodRef($food)]))
        ->and($t->mode)->toBe('database')
        ->and($t->error_code)->toBeNull()
        ->and($s->active_turn_id)->toBeNull()->and($s->active_token)->toBeNull()
        ->and($s->active_lease_expires_at)->toBeNull()
        ->and($s->last_food_id)->toBe($food->id);
});

test('finalize accepts a turn id string', function () {
    $session = ctSession();
    $turn = ctReserve($session)->turn;

    expect(ctGate()->finalize($turn->id, ctResponse('general'), [], []))->toBeTrue()
        ->and(ctFresh($turn)->status)->toBe('completed')
        ->and(ctFresh($session)->last_food_id)->toBeNull();
});

test('double finalize is a no-op that returns false', function () {
    $session = ctSession();
    $turn = ctReserve($session)->turn;
    ctGate()->finalize($turn, ctResponse('general'), [], []);
    $before = ctFresh($turn)->toArray();

    expect(ctGate()->finalize($turn, ['mode' => 'general', 'answer' => 'lain'], [], []))->toBeFalse()
        ->and(ctFresh($turn)->toArray())->toBe($before);
});

test('finalize after the lease expired writes nothing and returns false', function () {
    $session = ctSession();
    $turn = ctReserve($session)->turn;
    test()->travelTo('2026-11-08 10:00:35');

    expect(ctGate()->finalize($turn, ctResponse('general'), [], []))->toBeFalse()
        ->and(ctFresh($turn)->status)->toBe('pending')
        ->and(ctFresh($turn)->response)->toBeNull()
        ->and(ctFresh($session)->active_turn_id)->toBe($turn->id);
});

test('a provider-late result after session delete writes nothing and never resurrects history', function () {
    $food = cfFood();
    $session = ctSession();
    $turn = ctReserve($session)->turn;
    app(SessionEraser::class)->erase($session);
    $before = ctFresh($turn)->toArray();

    expect(ctGate()->finalize($turn, ctResponse(), [ctCitation($food)], [ctFoodRef($food)]))->toBeFalse()
        ->and(ctGate()->fail($turn, 'AI_PROVIDER_ERROR'))->toBeFalse()
        ->and(ctFresh($turn)->toArray())->toBe($before)
        ->and(ctFresh($turn)->message)->toBeNull()
        ->and(ctFresh($turn)->response)->toBeNull()
        ->and(ctFresh($session)->state)->toBe('deleted')
        ->and(ctFresh($session)->last_food_id)->toBeNull();
});

test('finalize after the session row vanished returns false', function () {
    $session = ctSession();
    $turn = ctReserve($session)->turn;
    ChatSession::whereKey($session->id)->delete();

    expect(ctGate()->finalize($turn, ctResponse('general'), [], []))->toBeFalse()
        ->and(ctGate()->fail($turn->id, 'AI_TIMEOUT'))->toBeFalse();
});

test('finalize refuses when a cited food was revoked: turn failed KNOWLEDGE_CHANGED, slot released', function () {
    $food = cfFood(['current_revision' => 3]);
    $session = ctSession();
    $turn = ctReserve($session)->turn;
    $food->forceFill(['status' => 'revoked', 'current_revision' => 4, 'search_text' => null])->save();

    $e = cfThrown(fn () => ctGate()->finalize($turn, ctResponse(), [ctCitation($food, 3)], [ctFoodRef($food, 3)]));
    $t = ctFresh($turn);

    expect($e->errorCode)->toBe('KNOWLEDGE_CHANGED')->and($e->retryable)->toBeTrue()
        ->and($t->status)->toBe('failed')
        ->and($t->error_code)->toBe('KNOWLEDGE_CHANGED')
        ->and($t->response)->toBeNull()->and($t->citations)->toBeNull()
        ->and(ctFresh($session)->active_turn_id)->toBeNull()
        ->and(ctReserve($session)->error()->errorCode)->toBe('KNOWLEDGE_CHANGED');
});

test('finalize refuses when a cited food changed revision even though it is still verified', function () {
    $food = cfFood(['current_revision' => 3]);
    $session = ctSession();
    $turn = ctReserve($session)->turn;
    $food->forceFill(['current_revision' => 5])->save();

    expect(cfThrown(fn () => ctGate()->finalize($turn, ctResponse(), [ctCitation($food, 3)], []))->errorCode)->toBe('KNOWLEDGE_CHANGED')
        ->and(ctFresh($turn)->status)->toBe('failed');
});

test('finalize refuses a food ref that is stale or unknown even without a citation', function () {
    $session = ctSession();
    $turn = ctReserve($session)->turn;
    $missing = ['food_id' => '10000000-0000-4000-8000-00000000dead', 'revision' => 1];

    expect(cfThrown(fn () => ctGate()->finalize($turn, ctResponse(), [], [$missing]))->errorCode)->toBe('KNOWLEDGE_CHANGED');
});

test('fail marks the owned turn failed, releases its slot and is idempotent', function () {
    $session = ctSession();
    $turn = ctReserve($session)->turn;

    expect(ctGate()->fail($turn, 'AI_PROVIDER_ERROR'))->toBeTrue()
        ->and(ctGate()->fail($turn, 'AI_TIMEOUT'))->toBeFalse()
        ->and(ctFresh($turn)->status)->toBe('failed')
        ->and(ctFresh($turn)->error_code)->toBe('AI_PROVIDER_ERROR')
        ->and(ctFresh($session)->active_turn_id)->toBeNull()
        ->and(ctGate()->finalize($turn, ctResponse('general'), [], []))->toBeFalse()
        ->and(ctFresh($turn)->status)->toBe('failed');
});

test('fail of a taken-over turn leaves the new owner slot untouched', function () {
    $session = ctSession();
    $old = ctReserve($session)->turn;
    test()->travelTo('2026-11-08 10:01:00');
    $new = ctReserve($session, 2, 'hash-b')->turn;

    expect(ctGate()->fail($old, 'AI_PROVIDER_ERROR'))->toBeFalse()
        ->and(ctFresh($old)->error_code)->toBe('AI_TIMEOUT')
        ->and(ctFresh($session)->active_turn_id)->toBe($new->id)
        ->and(ctFresh($session)->active_token)->toBe($new->token)
        // The old worker's late result cannot complete either.
        ->and(ctGate()->finalize($old, ctResponse('general'), [], []))->toBeFalse()
        ->and(ctGate()->finalize($new, ctResponse('general'), [], []))->toBeTrue();
});

test('finalize with a forged token writes nothing', function () {
    $session = ctSession();
    $turn = ctReserve($session)->turn;
    $forged = ctFresh($turn);
    $forged->token = 'forged';

    expect(ctGate()->finalize($forged, ctResponse('general'), [], []))->toBeFalse()
        ->and(ctFresh($turn)->status)->toBe('pending');
});

test('two food refs leave last_food_id empty: follow-ups carry a food only when exactly one was identified', function () {
    $a = cfFood(['name' => 'Tahu contoh']);
    $b = cfFood(['name' => 'Tempe contoh']);
    $session = ctSession(attrs: ['last_food_id' => $a->id]);
    $turn = ctReserve($session)->turn;

    ctGate()->finalize($turn, ctResponse(), [], [ctFoodRef($a), ctFoodRef($b)]);

    expect(ctFresh($session)->last_food_id)->toBeNull();
});
