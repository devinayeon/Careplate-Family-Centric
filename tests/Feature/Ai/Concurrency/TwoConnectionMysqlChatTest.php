<?php

use App\Actions\Foods\ReviewFood;
use App\Exceptions\Ai\AiException;
use App\Services\Ai\Chat\SessionEraser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/TwoConnectionMysqlSupport.php';
require_once __DIR__.'/../ChatTurnSupport.php';
require_once __DIR__.'/../ReviewFoodSupport.php';

// Chat side of spec 06 section 4 on real InnoDB (REPEATABLE READ): two connections to careplate_ai_cc. Interleaving
// is a DB::listen hook: B runs while A is inside its transaction, right after a named statement of A. One process,
// so a B that needs a row A holds cannot wait for A's commit: it fails after the 1 s lock wait timeout, and that
// failure must surface as a retryable SESSION_BUSY / DB_BUSY.

beforeEach(function () {
    $this->cmDefault = DB::getDefaultConnection();
    $skip = cmBoot();
    if ($skip !== null) {
        test()->markTestSkipped($skip);
    }
    test()->travelTo('2026-11-08 10:00:00');
});

afterEach(fn () => cmShutdown($this->cmDefault));

test('cc_a and cc_b are distinct handles on careplate_ai_cc at 127.0.0.1:3407 with a 1 s lock wait', function () {
    $session = ctSession(ctUser(''));

    foreach (['cc_a', 'cc_b'] as $name) {
        $c = config('database.connections.'.$name);
        expect(aiMysqlTargetError($c['driver'], $c['host'], $c['port'], $c['database'], $c['unix_socket'], $c['url']))->toBeNull()
            ->and(array_intersect(array_keys($c), ['read', 'write', 'sticky']))->toBe([])
            ->and($c['options'])->toBe([PDO::ATTR_TIMEOUT => 2])
            ->and(DB::connection($name)->selectOne('select database() as db')->db)->toBe('careplate_ai_cc')
            ->and((int) DB::connection($name)->selectOne('select @@session.innodb_lock_wait_timeout as t')->t)->toBe(1);
    }
    expect(ccPdoId('cc_a'))->not->toBe(ccPdoId('cc_b'))
        ->and(DB::connection('cc_b')->table('chat_sessions')->where('id', $session->id)->exists())->toBeTrue();
});

test('competing reserve: B blocked by A while A claims the slot is SESSION_BUSY; exactly one new turn, sequence unique', function () {
    $session = ctSession(ctUser(''));
    $probe = cmAfter('cc_a', 'update `chat_sessions`', fn () => ccOn('cc_b', fn () => ctReserve($session, 2)));

    $turnA = ctReserve($session, 1)->turn;
    $afterCommit = rfThrown(fn () => ccOn('cc_b', fn () => ctReserve($session, 2)));

    expect($probe->ran)->toBeTrue()
        ->and($probe->outcome)->toBeInstanceOf(AiException::class)
        ->and($probe->outcome->errorCode)->toBe('SESSION_BUSY')->and($probe->outcome->retryable)->toBeTrue()
        ->and($afterCommit->errorCode)->toBe('SESSION_BUSY')
        ->and(array_map('intval', DB::connection('cc_b')->table('chat_turns')->pluck('sequence')->all()))->toBe([1])
        ->and((int) DB::connection('cc_b')->table('chat_sessions')->value('next_sequence'))->toBe(2)
        ->and(DB::connection('cc_b')->table('chat_sessions')->value('active_turn_id'))->toBe($turnA->id);
});

test('finalize vs delete: a delete committed first makes finalize write nothing and return false', function () {
    $food = cfFood();
    $session = ctSession(ctUser(''));
    $turn = ctReserve($session, 1)->turn;

    ccOn('cc_b', fn () => app(SessionEraser::class)->erase($session));
    $before = (array) DB::connection('cc_b')->table('chat_turns')->where('id', $turn->id)->first();

    expect(ctGate()->finalize($turn, ctResponse(), [ctCitation($food)], [ctFoodRef($food)]))->toBeFalse()
        ->and((array) DB::connection('cc_a')->table('chat_turns')->where('id', $turn->id)->first())->toBe($before)
        ->and($before['response'])->toBeNull()
        ->and(DB::connection('cc_a')->table('chat_sessions')->value('state'))->toBe('deleted');
});

test('finalize vs delete: a delete while finalize holds the session lock is SESSION_BUSY; finalize completes, a retry erases it', function () {
    $session = ctSession(ctUser(''));
    $turn = ctReserve($session, 1)->turn;
    $probe = cmAfter('cc_a', 'select * from `chat_turns`', fn () => ccOn('cc_b', fn () => app(SessionEraser::class)->erase($session)));

    $finalized = ctGate()->finalize($turn, ctResponse('general'), [], []);

    expect($probe->ran)->toBeTrue()
        ->and($finalized)->toBeTrue()
        ->and($probe->outcome)->toBeInstanceOf(AiException::class)
        ->and($probe->outcome->errorCode)->toBe('SESSION_BUSY')->and($probe->outcome->retryable)->toBeTrue()
        ->and(DB::connection('cc_b')->table('chat_turns')->value('status'))->toBe('completed');

    ccOn('cc_b', fn () => app(SessionEraser::class)->erase($session));
    expect(DB::connection('cc_a')->table('chat_turns')->value('response'))->toBeNull()
        ->and(DB::connection('cc_a')->table('chat_sessions')->value('state'))->toBe('deleted');
});

test('finalize after a revoke committed by B inside A\'s transaction is KNOWLEDGE_CHANGED (current read, no snapshot)', function () {
    $food = cfFood(['current_revision' => 3]);
    $manifest = rfManifest();
    $session = ctSession(ctUser(''));
    $turn = ctReserve($session, 1)->turn;
    $probe = cmAfter('cc_a', 'select * from `chat_turns`', fn () => ccOn('cc_b', fn () => (new ReviewFood($manifest))->revoke($food->id, null, 'audit-b', 3, 'Dicabut untuk uji')));

    $error = cmCaught(fn () => ctGate()->finalize($turn, ctResponse(), [ctCitation($food, 3)], [ctFoodRef($food, 3)]));
    $row = DB::connection('cc_b')->table('chat_turns')->where('id', $turn->id)->first();
    $slot = DB::connection('cc_b')->table('chat_sessions')->first();

    expect($probe->outcome)->toBeArray()->and($probe->outcome['status'])->toBe('revoked')
        ->and($error)->toBeInstanceOf(AiException::class)
        ->and($error?->errorCode)->toBe('KNOWLEDGE_CHANGED')
        ->and($row->status)->toBe('failed')->and($row->error_code)->toBe('KNOWLEDGE_CHANGED')
        ->and($row->response)->toBeNull()
        ->and($slot->active_turn_id)->toBeNull()->and($slot->last_food_id)->toBeNull();
});

test('a revoke while finalize holds its read lock on the cited food is DB_BUSY; finalize completes, the retried revoke wins next', function () {
    $food = cfFood(['current_revision' => 3]);
    $manifest = rfManifest();
    $session = ctSession(ctUser(''));
    $turn = ctReserve($session, 1)->turn;
    $probe = cmAfter('cc_a', 'select `current_revision`', fn () => ccOn('cc_b', fn () => (new ReviewFood($manifest))->revoke($food->id, null, 'audit-b', 3, 'Dicabut untuk uji')));

    $finalized = ctGate()->finalize($turn, ctResponse(), [ctCitation($food, 3)], [ctFoodRef($food, 3)]);
    $retry = ccOn('cc_b', fn () => (new ReviewFood($manifest))->revoke($food->id, null, 'audit-b', 3, 'Dicabut untuk uji'));

    expect($probe->ran)->toBeTrue()
        ->and($probe->outcome)->toBeInstanceOf(AiException::class)
        ->and($probe->outcome->errorCode)->toBe('DB_BUSY')->and($probe->outcome->retryable)->toBeTrue()
        ->and($finalized)->toBeTrue()
        ->and($retry['status'])->toBe('revoked')->and($retry['revision'])->toBe(4);
});

test('a lock wait timeout in reserve, finalize, fail and delete is a retryable SESSION_BUSY and writes nothing', function () {
    $user = ctUser('');
    $busy = ctSession($user);
    $free = ctSession($user);
    $turn = ctReserve($busy, 1)->turn;
    $blocker = DB::connection('cc_b');
    $blocker->beginTransaction();
    $blocker->table('chat_sessions')->whereIn('id', [$busy->id, $free->id])->lockForUpdate()->get();

    $started = microtime(true);
    $errors = [
        'reserve' => cmCaught(fn () => ctReserve($free, 2)),
        'finalize' => cmCaught(fn () => ctGate()->finalize($turn, ctResponse('general'), [], [])),
        'fail' => cmCaught(fn () => ctGate()->fail($turn, 'AI_TIMEOUT')),
        'erase' => cmCaught(fn () => app(SessionEraser::class)->erase($busy)),
    ];
    $elapsed = microtime(true) - $started;
    $blocker->rollBack();

    foreach ($errors as $error) {
        expect($error)->toBeInstanceOf(AiException::class)
            ->and($error->errorCode)->toBe('SESSION_BUSY')->and($error->retryable)->toBeTrue();
    }
    expect($elapsed)->toBeGreaterThan(3.5)->toBeLessThan(20.0)
        ->and(DB::connection('cc_a')->table('chat_turns')->count())->toBe(1)
        ->and(DB::connection('cc_a')->table('chat_turns')->value('status'))->toBe('pending')
        ->and(DB::connection('cc_a')->table('chat_sessions')->where('state', 'active')->count())->toBe(2)
        ->and(ctGate()->finalize($turn, ctResponse('general'), [], []))->toBeTrue();
});

test('purge re-checks the lease inside its delete: a session that gains a lease mid-chunk keeps its turns', function () {
    config(['ai.chat.retention_days' => 7, 'ai.chat.lease_seconds' => 35, 'ai.chat.deadline_seconds' => 20]);
    $user = ctUser('');
    $leased = ctSession($user, ['next_sequence' => 2]);
    $idle = ctSession($user, ['next_sequence' => 2]);
    $old = [ctTurn($leased, 1), ctTurn($idle, 1)];
    DB::table('chat_turns')->update(['created_at' => '2026-10-01 10:00:00', 'updated_at' => '2026-10-01 10:00:00']);
    $probe = cmAfter('cc_a', 'select `id` from `chat_turns`', fn () => ccOn('cc_b', fn () => ctReserve($leased, 5)));

    $code = Artisan::call('ai:purge-chats');

    expect($code)->toBe(0)
        ->and($probe->ran)->toBeTrue()
        ->and(Artisan::output())->toContain('deleted turns 1, sessions 0')
        ->and(DB::connection('cc_b')->table('chat_turns')->where('id', $old[0]->id)->exists())->toBeTrue()
        ->and(DB::connection('cc_b')->table('chat_turns')->where('id', $old[1]->id)->exists())->toBeFalse()
        ->and(DB::connection('cc_b')->table('chat_turns')->where('id', $probe->outcome->turn->id)->value('status'))->toBe('pending');
});
