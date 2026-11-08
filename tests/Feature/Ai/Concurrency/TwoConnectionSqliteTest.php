<?php

use App\Actions\Foods\ReviewFood;
use App\Exceptions\Ai\AiException;
use App\Services\Ai\Chat\SessionEraser;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/TwoConnectionSupport.php';
require_once __DIR__.'/../ChatTurnSupport.php';
require_once __DIR__.'/../ReviewFoodSupport.php';

// Spec 06 section 4: two real PDO connections (cc_a, cc_b) to one temp SQLite file. Interleaving is controlled:
// either B commits fully before A acts, or B is invoked from a DB::listen hook WHILE A holds its IMMEDIATE write
// lock (A and B are both open PDO handles in one process, so B truly hits SQLITE_BUSY after the 200 ms busy_timeout).

beforeEach(function () {
    $this->ccPath = null; // defined even if ccBoot() throws, so afterEach never hits an undefined property
    $this->ccPath = ccBoot();
    test()->travelTo('2026-11-08 10:00:00');
});

afterEach(fn () => ccShutdown($this->ccPath));

/** Runs $competing once, the first time cc_a executes a statement starting with $sqlPrefix (A is inside its transaction). */
function ccWhenAWrites(string $sqlPrefix, Closure $competing): object
{
    $probe = (object) ['armed' => true, 'outcome' => null, 'ran' => false];
    DB::listen(function ($query) use ($probe, $sqlPrefix, $competing) {
        if ($probe->armed && $query->connectionName === 'cc_a' && str_starts_with($query->sql, $sqlPrefix)) {
            $probe->armed = false;
            $probe->ran = true;
            try {
                $probe->outcome = $competing();
            } catch (Throwable $e) {
                $probe->outcome = $e;
            }
        }
    });

    return $probe;
}

function ccCount(string $connection, string $table): int
{
    return DB::connection($connection)->table($table)->count();
}

test('the two connections are distinct PDO handles on one file and see each other committed rows', function () {
    $session = ctSession(ctUser(''));

    expect(ccPdoId('cc_a'))->not->toBe(ccPdoId('cc_b'))
        ->and(DB::connection('cc_a')->getDatabaseName())->toBe(DB::connection('cc_b')->getDatabaseName())
        ->and(DB::connection('cc_a')->getDatabaseName())->toBe($this->ccPath)
        ->and(DB::connection('cc_b')->table('chat_sessions')->where('id', $session->id)->exists())->toBeTrue();
});

test('(a) a competing claim from B after A committed is SESSION_BUSY and a replay of A is TURN_IN_PROGRESS', function () {
    $session = ctSession(ctUser(''));
    $turnA = ctReserve($session, 1)->turn;

    $busy = rfThrown(fn () => ccOn('cc_b', fn () => ctReserve($session, 2)));
    $replay = rfThrown(fn () => ccOn('cc_b', fn () => ctReserve($session, 1)));

    expect($busy->errorCode)->toBe('SESSION_BUSY')->and($busy->retryable)->toBeTrue()
        ->and($replay->errorCode)->toBe('TURN_IN_PROGRESS')
        ->and(ccCount('cc_b', 'chat_turns'))->toBe(1)
        ->and(DB::connection('cc_b')->table('chat_turns')->value('sequence'))->toBe(1)
        ->and(DB::connection('cc_b')->table('chat_sessions')->value('active_turn_id'))->toBe($turnA->id);
});

test('(a) B claiming WHILE A holds the write lock gets a controlled SESSION_BUSY; exactly one new turn, sequence unique', function () {
    $session = ctSession(ctUser(''));
    $probe = ccWhenAWrites('insert into "chat_turns"', fn () => ccOn('cc_b', fn () => ctReserve($session, 2)));

    $turnA = ctReserve($session, 1)->turn;
    $afterCommit = rfThrown(fn () => ccOn('cc_b', fn () => ctReserve($session, 2)));

    expect($probe->ran)->toBeTrue()
        ->and($probe->outcome)->toBeInstanceOf(AiException::class)
        ->and($probe->outcome->errorCode)->toBe('SESSION_BUSY')
        ->and($afterCommit->errorCode)->toBe('SESSION_BUSY')
        ->and(ccCount('cc_b', 'chat_turns'))->toBe(1)
        ->and(DB::connection('cc_b')->table('chat_turns')->pluck('sequence')->all())->toBe([1])
        ->and((int) DB::connection('cc_b')->table('chat_sessions')->value('next_sequence'))->toBe(2)
        ->and(DB::connection('cc_b')->table('chat_sessions')->value('active_turn_id'))->toBe($turnA->id);
});

test('(a) the unique(session_id, sequence) index rejects a duplicate sequence written from the other connection', function () {
    $session = ctSession(ctUser(''));
    ctReserve($session, 1);

    $duplicate = fn () => DB::connection('cc_b')->table('chat_turns')->insert([
        'id' => (string) Str::uuid(), 'session_id' => $session->id, 'client_turn_id' => ctKey(9), 'sequence' => 1,
        'request_hash' => 'x', 'status' => 'pending', 'token' => 't', 'generation' => 1,
    ]);

    expect($duplicate)->toThrow(UniqueConstraintViolationException::class)
        ->and(ccCount('cc_a', 'chat_turns'))->toBe(1);
});

test('(b) a session delete committed on B first: A finalize writes nothing and returns false', function () {
    $food = cfFood();
    $session = ctSession(ctUser(''));
    $turn = ctReserve($session, 1)->turn;

    ccOn('cc_b', fn () => app(SessionEraser::class)->erase($session));
    $before = (array) DB::connection('cc_b')->table('chat_turns')->where('id', $turn->id)->first();

    expect(ctGate()->finalize($turn, ctResponse(), [ctCitation($food)], [ctFoodRef($food)]))->toBeFalse()
        ->and((array) DB::connection('cc_a')->table('chat_turns')->where('id', $turn->id)->first())->toBe($before)
        ->and($before['response'])->toBeNull()
        ->and(DB::connection('cc_a')->table('chat_sessions')->value('state'))->toBe('deleted')
        ->and(DB::connection('cc_a')->table('chat_sessions')->value('last_food_id'))->toBeNull();
});

test('(b) a delete attempted while A finalizes is a controlled SESSION_BUSY; A completes, the later delete erases it', function () {
    $session = ctSession(ctUser(''));
    $turn = ctReserve($session, 1)->turn;
    $probe = ccWhenAWrites('update "chat_sessions"', fn () => ccOn('cc_b', fn () => app(SessionEraser::class)->erase($session)));

    $finalized = ctGate()->finalize($turn, ctResponse('general'), [], []);
    expect($finalized)->toBeTrue()
        ->and($probe->outcome)->toBeInstanceOf(AiException::class)
        ->and($probe->outcome->errorCode)->toBe('SESSION_BUSY')
        ->and(DB::connection('cc_b')->table('chat_turns')->value('status'))->toBe('completed');

    ccOn('cc_b', fn () => app(SessionEraser::class)->erase($session));
    expect(DB::connection('cc_a')->table('chat_turns')->value('response'))->toBeNull()
        ->and(DB::connection('cc_a')->table('chat_sessions')->value('state'))->toBe('deleted');
});

test('(c) a food revoked on B before A finalizes: KNOWLEDGE_CHANGED, turn failed, slot released, no completion', function () {
    $food = cfFood(['current_revision' => 3]);
    $session = ctSession(ctUser(''));
    $turn = ctReserve($session, 1)->turn;

    ccOn('cc_b', fn () => (new ReviewFood(rfManifest()))->revoke($food->id, null, 'audit-b', 3, 'Dicabut untuk uji'));

    $error = rfThrown(fn () => ctGate()->finalize($turn, ctResponse(), [ctCitation($food, 3)], [ctFoodRef($food, 3)]));
    $row = DB::connection('cc_b')->table('chat_turns')->where('id', $turn->id)->first();
    $slot = DB::connection('cc_b')->table('chat_sessions')->first();

    expect($error->errorCode)->toBe('KNOWLEDGE_CHANGED')
        ->and($row->status)->toBe('failed')->and($row->error_code)->toBe('KNOWLEDGE_CHANGED')
        ->and($row->response)->toBeNull()->and($row->citations)->toBeNull()
        ->and($slot->active_turn_id)->toBeNull()->and($slot->last_food_id)->toBeNull();
});

test('(d) revoke with the same expected revision from A and B: exactly one wins, the other STALE_REVISION', function () {
    $food = cfFood(['current_revision' => 3]);
    $manifest = rfManifest();

    $first = ccOn('cc_a', fn () => (new ReviewFood($manifest))->revoke($food->id, null, 'audit-a', 3, 'Alasan A'));
    $second = rfThrown(fn () => ccOn('cc_b', fn () => (new ReviewFood($manifest))->revoke($food->id, null, 'audit-b', 3, 'Alasan B')));

    expect($first['revision'])->toBe(4)->and($first['status'])->toBe('revoked')
        ->and($second->errorCode)->toBe('STALE_REVISION')
        ->and(DB::connection('cc_b')->table('foods')->value('current_revision'))->toBe(4)
        ->and(DB::connection('cc_b')->table('food_revisions')->where('action', 'revoke')->pluck('actor_audit_id')->all())->toBe(['audit-a']);
});

test('(d) approve with the same expected revision from A and B: exactly one wins, the other STALE_REVISION', function () {
    $manifest = rfManifest();
    $submission = rfSubmission(['revision_counter' => 2]);

    $first = ccOn('cc_a', fn () => (new ReviewFood($manifest))->approve($submission->id, null, 'audit-a', 2, 'Setuju A'));
    $second = rfThrown(fn () => ccOn('cc_b', fn () => (new ReviewFood($manifest))->approve($submission->id, null, 'audit-b', 2, 'Setuju B')));

    expect($first['status'])->toBe('verified')->and($first['revision'])->toBe(1)
        ->and($second->errorCode)->toBe('STALE_REVISION')
        ->and(ccCount('cc_b', 'foods'))->toBe(1)
        ->and(ccCount('cc_b', 'food_revisions'))->toBe(1)
        ->and(DB::connection('cc_b')->table('food_submissions')->value('status'))->toBe('approved');
});

test('(d) a revoke attempted by B while A is mid-revoke fails on the lock without partial state; then B is STALE_REVISION', function () {
    $food = cfFood(['current_revision' => 3]);
    $manifest = rfManifest();
    $probe = ccWhenAWrites('insert into "food_revisions"', fn () => ccOn('cc_b', fn () => (new ReviewFood($manifest))->revoke($food->id, null, 'audit-b', 3, 'Alasan B')));

    ccOn('cc_a', fn () => (new ReviewFood($manifest))->revoke($food->id, null, 'audit-a', 3, 'Alasan A'));
    $after = rfThrown(fn () => ccOn('cc_b', fn () => (new ReviewFood($manifest))->revoke($food->id, null, 'audit-b', 3, 'Alasan B')));

    // ReviewFood has no busy mapping (known limit): the lock surfaces raw, but nothing of B was written.
    expect($probe->outcome)->toBeInstanceOf(Throwable::class)
        ->and($probe->outcome->getMessage())->toContain('database is locked')
        ->and($after->errorCode)->toBe('STALE_REVISION')
        ->and(DB::connection('cc_b')->table('food_revisions')->where('action', 'revoke')->count())->toBe(1);
});

test('(e) a write blocked by an open IMMEDIATE transaction on B becomes a retryable SESSION_BUSY, not a raw exception', function () {
    $session = ctSession(ctUser(''));
    $turn = ctReserve($session, 1)->turn;
    $blocker = DB::connection('cc_b');
    $blocker->beginTransaction();
    $blocker->table('chat_sessions')->update(['title' => 'lock']);

    $started = microtime(true);
    $reserve = rfThrown(fn () => ctReserve($session, 2));
    $finalize = rfThrown(fn () => ctGate()->finalize($turn, ctResponse('general'), [], []));
    $erase = rfThrown(fn () => app(SessionEraser::class)->erase($session));
    $elapsed = microtime(true) - $started;
    $blocker->rollBack();

    expect($reserve->errorCode)->toBe('SESSION_BUSY')->and($reserve->retryable)->toBeTrue()
        ->and($finalize->errorCode)->toBe('SESSION_BUSY')
        ->and($erase->errorCode)->toBe('SESSION_BUSY')
        ->and($elapsed)->toBeGreaterThan(0.5)->toBeLessThan(5.0)
        ->and(DB::connection('cc_a')->table('chat_turns')->count())->toBe(1)
        ->and(DB::connection('cc_a')->table('chat_turns')->value('status'))->toBe('pending')
        ->and(DB::connection('cc_a')->table('chat_sessions')->value('title'))->toBeNull()
        ->and(ctGate()->finalize($turn, ctResponse('general'), [], []))->toBeTrue();
});
