<?php

use App\Models\ChatTurn;
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

test('reserve creates a pending turn, takes the slot and advances next_sequence', function () {
    $session = ctSession();

    $r = ctReserve($session);
    $s = ctFresh($session);

    expect($r->isNew())->toBeTrue()->and($r->isReplay())->toBeFalse()
        ->and($r->response())->toBeNull()->and($r->error())->toBeNull()
        ->and($r->turn->status)->toBe('pending')
        ->and($r->turn->sequence)->toBe(1)
        ->and($r->turn->generation)->toBe(1)
        ->and($r->turn->client_turn_id)->toBe(ctKey(1))
        ->and($r->turn->request_hash)->toBe('hash-a')
        ->and($r->turn->message)->toBe('Berapa protein tahu?')
        ->and(strlen($r->turn->token))->toBeGreaterThanOrEqual(32)
        ->and($r->turn->lease_expires_at->toDateTimeString())->toBe('2026-11-08 10:00:35')
        ->and($s->active_turn_id)->toBe($r->turn->id)
        ->and($s->active_token)->toBe($r->turn->token)
        ->and($s->active_lease_expires_at->toDateTimeString())->toBe('2026-11-08 10:00:35')
        ->and($s->next_sequence)->toBe(2);
});

test('sequential reserves get distinct sequences and never break unique(session_id, sequence)', function () {
    $session = ctSession();

    $first = ctReserve($session, 1);
    ctGate()->finalize($first->turn, ctResponse('general'), [], []);
    $second = ctReserve($session, 2, 'hash-b');
    ctGate()->fail($second->turn, 'AI_PROVIDER_ERROR');
    $third = ctReserve(ctFresh($session), 3, 'hash-c');

    expect([$first->turn->sequence, $second->turn->sequence, $third->turn->sequence])->toBe([1, 2, 3])
        ->and(ChatTurn::where('session_id', $session->id)->pluck('sequence')->sort()->values()->all())->toBe([1, 2, 3])
        ->and(ctFresh($session)->next_sequence)->toBe(4);
});

test('same key with a different hash is 409 IDEMPOTENCY_CONFLICT whatever the turn state', function () {
    $session = ctSession();
    ctReserve($session);

    $e = cfThrown(fn () => ctReserve($session, 1, 'hash-other'));

    expect($e->errorCode)->toBe('IDEMPOTENCY_CONFLICT')->and($e->status)->toBe(409)
        ->and(ChatTurn::count())->toBe(1);
});

test('same key and hash on a completed turn replays the stored response without new rows', function () {
    $session = ctSession();
    $turn = ctReserve($session)->turn;
    ctGate()->finalize($turn, ctResponse('general'), [], []);

    $r = ctReserve($session);

    expect($r->isReplay())->toBeTrue()->and($r->isNew())->toBeFalse()
        ->and($r->turn->id)->toBe($turn->id)
        ->and(dbCanonical($r->response()))->toBe(dbCanonical(ctResponse('general')))
        ->and($r->error())->toBeNull()
        ->and(ChatTurn::count())->toBe(1)
        ->and(ctFresh($session)->next_sequence)->toBe(2);
});

test('same key on a failed turn replays the stored error; a new attempt needs a new key', function () {
    $session = ctSession();
    $turn = ctReserve($session)->turn;
    ctGate()->fail($turn, 'AI_PROVIDER_ERROR');

    $r = ctReserve($session);

    expect($r->isReplay())->toBeTrue()->and($r->response())->toBeNull()
        ->and($r->error()->errorCode)->toBe('AI_PROVIDER_ERROR')
        ->and($r->error()->status)->toBe(502)
        ->and(ctReserve($session, 2, 'hash-b')->isNew())->toBeTrue();
});

test('same key while pending with a valid lease is 409 TURN_IN_PROGRESS', function () {
    $session = ctSession();
    ctReserve($session);
    test()->travelTo('2026-11-08 10:00:34');

    $e = cfThrown(fn () => ctReserve($session));

    expect($e->errorCode)->toBe('TURN_IN_PROGRESS')->and($e->status)->toBe(409);
});

test('same key while pending with an expired lease closes it as AI_TIMEOUT before any 409', function () {
    $session = ctSession();
    $turn = ctReserve($session)->turn;
    test()->travelTo('2026-11-08 10:00:35');

    $r = ctReserve($session);
    $fresh = ctFresh($turn);
    $s = ctFresh($session);

    expect($r->isReplay())->toBeTrue()
        ->and($r->error()->errorCode)->toBe('AI_TIMEOUT')
        ->and($r->error()->status)->toBe(504)
        ->and($fresh->status)->toBe('failed')
        ->and($fresh->error_code)->toBe('AI_TIMEOUT')
        ->and($s->active_turn_id)->toBeNull()->and($s->active_token)->toBeNull()
        ->and($s->active_lease_expires_at)->toBeNull()
        // The replay is stable: the stored terminal error, never TURN_IN_PROGRESS.
        ->and(ctReserve($session)->error()->errorCode)->toBe('AI_TIMEOUT');
});

test('expired same-key close leaves a slot that another turn already owns untouched', function () {
    $session = ctSession();
    $old = ctReserve($session)->turn;
    // Simulate an inconsistent takeover: the slot points at another turn with a valid lease.
    $session->newQuery()->whereKey($session->id)->update([
        'active_turn_id' => 'aaaaaaaa-0000-4000-8000-000000000000', 'active_token' => 'other-token',
        'active_lease_expires_at' => '2026-11-08 10:05:00',
    ]);
    test()->travelTo('2026-11-08 10:01:00');

    $r = ctReserve($session);
    $s = ctFresh($session);

    expect($r->error()->errorCode)->toBe('AI_TIMEOUT')
        ->and(ctFresh($old)->status)->toBe('failed')
        ->and($s->active_turn_id)->toBe('aaaaaaaa-0000-4000-8000-000000000000')
        ->and($s->active_token)->toBe('other-token');
});

test('a different key while the slot is held with a valid lease is 409 SESSION_BUSY', function () {
    $session = ctSession();
    $held = ctReserve($session)->turn;
    test()->travelTo('2026-11-08 10:00:20');

    $e = cfThrown(fn () => ctReserve($session, 2, 'hash-b'));

    expect($e->errorCode)->toBe('SESSION_BUSY')->and($e->status)->toBe(409)
        ->and(ChatTurn::count())->toBe(1)
        ->and(ctFresh($session)->active_turn_id)->toBe($held->id)
        ->and(ctFresh($session)->next_sequence)->toBe(2);
});

test('an expired foreign slot is closed as AI_TIMEOUT and released before the new turn proceeds', function () {
    $session = ctSession();
    $stale = ctReserve($session)->turn;
    test()->travelTo('2026-11-08 10:01:00');

    $r = ctReserve($session, 2, 'hash-b');
    $s = ctFresh($session);

    expect($r->isNew())->toBeTrue()
        ->and($r->turn->sequence)->toBe(2)
        ->and(ctFresh($stale)->status)->toBe('failed')
        ->and(ctFresh($stale)->error_code)->toBe('AI_TIMEOUT')
        ->and($s->active_turn_id)->toBe($r->turn->id)
        ->and($s->active_token)->toBe($r->turn->token)
        ->and($s->active_lease_expires_at->toDateTimeString())->toBe('2026-11-08 10:01:35');
});

test('reserve on a deleted or vanished session is 404 NOT_FOUND', function () {
    $deleted = ctSession(attrs: ['state' => 'deleted', 'generation' => 2]);
    $gone = ctSession();
    $gone->newQuery()->whereKey($gone->id)->delete();

    expect(cfThrown(fn () => ctReserve($deleted))->errorCode)->toBe('NOT_FOUND')
        ->and(cfThrown(fn () => ctReserve($gone))->status)->toBe(404)
        ->and(ChatTurn::count())->toBe(0);
});

test('a stale session model passed to reserve is re-read inside the transaction', function () {
    $session = ctSession();
    $staleCopy = ctFresh($session);
    ctReserve($session, 1);

    $e = cfThrown(fn () => ctReserve($staleCopy, 2, 'hash-b'));

    expect($e->errorCode)->toBe('SESSION_BUSY')->and(ChatTurn::count())->toBe(1);
});

test('replays older than the retention window are not served', function () {
    $session = ctSession();
    ctGate()->finalize(ctReserve($session)->turn, ctResponse('general'), [], []);
    test()->travelTo('2026-11-15 10:00:01');

    $e = cfThrown(fn () => ctReserve($session));

    expect($e->errorCode)->toBe('IDEMPOTENCY_CONFLICT')
        ->and($e->getMessage())->not->toContain('Jawaban');
});
