<?php

use App\Console\Commands\Ai\PurgeChats;
use App\Models\ChatSession;
use App\Models\ChatTurn;
use App\Services\Ai\Chat\SessionEraser;
use Database\Seeders\AiPermissionSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// SYNTHETIC users, sessions and turns only. "Now" is frozen at 2026-11-08 10:00:00, retention is 7 days, so the
// cutoff is 2026-11-01 10:00:00. Tombstone horizon = max(lease 35 s, deadline 20 s) + 1 day = 86435 s.

require_once __DIR__.'/ChatTurnSupport.php';

const PG_NOW = '2026-11-08 10:00:00';
const PG_CUTOFF = '2026-11-01 10:00:00';
const PG_MARKER = 'RAHASIA-PENGGUNA-7731';

beforeEach(function () {
    test()->seed(AiPermissionSeeder::class);
    PurgeChats::$chunkSize = 200;
    test()->travelTo(PG_NOW);
    config([
        'ai.chat.retention_days' => 7, 'ai.chat.lease_seconds' => 35, 'ai.chat.deadline_seconds' => 20,
    ]);
});

/** Set both timestamps on a session or turn row directly (the model keeps them off the fillable list). */
function pgStamp(ChatSession|ChatTurn $row, string $created, ?string $updated = null): void
{
    DB::table($row->getTable())->where('id', $row->id)->update(['created_at' => $created, 'updated_at' => $updated ?? $created]);
}

function pgSession(string $updated, array $attrs = []): ChatSession
{
    $session = ctSession(null, $attrs);
    pgStamp($session, $updated);

    return $session;
}

function pgTurn(ChatSession $session, int $sequence, string $created, array $attrs = []): ChatTurn
{
    $turn = ctTurn($session, $sequence, $attrs);
    pgStamp($turn, $created);

    return $turn;
}

/** @return array{turns: int, sessions: int, output: string} */
function pgRun(bool $dry = false): array
{
    $code = Artisan::call('ai:purge-chats', $dry ? ['--dry-run' => true] : []);
    $output = Artisan::output();
    expect($code)->toBe(0);
    preg_match('/turns (\d+), sessions (\d+)/', $output, $m);

    return ['turns' => (int) ($m[1] ?? -1), 'sessions' => (int) ($m[2] ?? -1), 'output' => $output];
}

function pgExists(ChatSession|ChatTurn $row): bool
{
    return DB::table($row->getTable())->where('id', $row->id)->exists();
}

test('turns exactly retention_days old are kept, one second older are deleted (strict less-than)', function () {
    $session = pgSession(PG_NOW);
    $kept = pgTurn($session, 1, PG_CUTOFF);
    $gone = pgTurn($session, 2, '2026-11-01 09:59:59');
    $fresh = pgTurn($session, 3, PG_NOW);

    $r = pgRun();

    expect(pgExists($kept))->toBeTrue()->and(pgExists($gone))->toBeFalse()->and(pgExists($fresh))->toBeTrue()
        ->and($r['turns'])->toBe(1)->and($r['sessions'])->toBe(0)->and(pgExists($session))->toBeTrue();
});

test('expired turns of any status go, expired pending included, and a dangling slot is released', function () {
    $session = pgSession(PG_NOW);
    $done = pgTurn($session, 1, '2026-10-01 00:00:00');
    $failed = pgTurn($session, 2, '2026-10-01 00:00:00', ['status' => 'failed', 'error_code' => 'AI_TIMEOUT', 'response' => null]);
    $pending = pgTurn($session, 3, '2026-10-01 00:00:00', ['status' => 'pending', 'lease_expires_at' => '2026-10-01 00:00:35']);
    DB::table('chat_sessions')->where('id', $session->id)->update([
        'active_turn_id' => $pending->id, 'active_token' => $pending->token, 'active_lease_expires_at' => '2026-10-01 00:00:35',
    ]);

    $r = pgRun();
    $s = ctFresh($session);

    expect($r['turns'])->toBe(3)
        ->and(ChatTurn::count())->toBe(0)
        ->and($s->active_turn_id)->toBeNull()->and($s->active_token)->toBeNull()->and($s->active_lease_expires_at)->toBeNull()
        ->and(pgExists($done) || pgExists($failed) || pgExists($pending))->toBeFalse();
});

test('purging an expired turn never empties the slot of another turn', function () {
    $session = pgSession(PG_NOW);
    $old = pgTurn($session, 1, '2026-10-01 00:00:00');
    $live = pgTurn($session, 2, PG_NOW, ['status' => 'pending', 'lease_expires_at' => '2026-11-08 09:59:59']);   // expired lease, newer turn
    DB::table('chat_sessions')->where('id', $session->id)->update([
        'active_turn_id' => $live->id, 'active_token' => $live->token, 'active_lease_expires_at' => '2026-11-08 09:59:59',
    ]);

    pgRun();
    $s = ctFresh($session);

    expect(pgExists($old))->toBeFalse()->and(pgExists($live))->toBeTrue()
        ->and($s->active_turn_id)->toBe($live->id)->and($s->active_token)->toBe($live->token);
});

test('fresh sessions and their turns are never touched', function () {
    $withTurns = pgSession(PG_NOW);
    pgTurn($withTurns, 1, PG_NOW);
    $empty = pgSession('2026-11-07 10:00:00');

    $r = pgRun();

    expect($r)->toMatchArray(['turns' => 0, 'sessions' => 0])
        ->and(pgExists($withTurns))->toBeTrue()->and(pgExists($empty))->toBeTrue()->and(ChatTurn::count())->toBe(1);
});

test('an active session emptied by the purge is removed only when its own updated_at is also old', function () {
    $oldBoth = pgSession('2026-10-20 00:00:00');
    pgTurn($oldBoth, 1, '2026-10-20 00:00:00');
    $recentUpdate = pgSession('2026-11-07 00:00:00');
    pgTurn($recentUpdate, 1, '2026-10-20 00:00:00');
    $atCutoff = pgSession(PG_CUTOFF);
    pgTurn($atCutoff, 1, '2026-10-20 00:00:00');
    $neverUsed = pgSession('2026-10-01 00:00:00');
    $mixed = pgSession('2026-10-20 00:00:00');
    pgTurn($mixed, 1, '2026-10-20 00:00:00');
    pgTurn($mixed, 2, PG_NOW);

    $r = pgRun();

    expect($r)->toMatchArray(['turns' => 4, 'sessions' => 2])
        ->and(pgExists($oldBoth))->toBeFalse()->and(pgExists($neverUsed))->toBeFalse()
        ->and(pgExists($recentUpdate))->toBeTrue()->and(pgExists($atCutoff))->toBeTrue()
        ->and(pgExists($mixed))->toBeTrue()->and(ChatTurn::where('session_id', $mixed->id)->count())->toBe(1);
});

test('a deleted tombstone is kept before the request horizon and removed with its turns after it', function () {
    expect(PurgeChats::SAFETY_MARGIN_SECONDS)->toBe(86400);
    $before = pgSession('2026-11-07 09:59:26', ['state' => 'deleted', 'generation' => 2]);   // 86434 s ago
    pgTurn($before, 1, '2026-11-07 09:00:00', ['message' => null, 'response' => null]);
    $after = pgSession('2026-11-07 09:59:24', ['state' => 'deleted', 'generation' => 2]);    // 86436 s ago
    $afterTurn = pgTurn($after, 1, '2026-11-07 09:00:00', ['message' => null, 'response' => null, 'request_hash' => 'h-gone']);

    $r = pgRun();

    expect($r)->toMatchArray(['turns' => 1, 'sessions' => 1])
        ->and(pgExists($before))->toBeTrue()->and(ChatTurn::where('session_id', $before->id)->count())->toBe(1)
        ->and(pgExists($after))->toBeFalse()->and(pgExists($afterTurn))->toBeFalse()
        ->and(DB::table('chat_turns')->where('request_hash', 'h-gone')->exists())->toBeFalse();
});

test('the horizon follows the larger of lease_seconds and deadline_seconds', function () {
    config(['ai.chat.deadline_seconds' => 3600]);          // horizon becomes 3600 + 86400 = 90000 s
    $kept = pgSession('2026-11-07 09:00:00', ['state' => 'deleted', 'generation' => 2]);   // exactly 90000 s ago: not older, kept
    $gone = pgSession('2026-11-07 08:59:59', ['state' => 'deleted', 'generation' => 2]);   // 90001 s ago

    pgRun();

    expect(pgExists($kept))->toBeTrue()->and(pgExists($gone))->toBeFalse();
});

test('a session with a pending turn holding a valid lease is untouched, even when old', function () {
    $session = pgSession('2026-10-01 00:00:00');
    $old = pgTurn($session, 1, '2026-10-01 00:00:00');
    $live = pgTurn($session, 2, '2026-10-01 00:00:00', ['status' => 'pending', 'lease_expires_at' => '2026-11-08 10:00:01']);
    $tomb = pgSession('2026-10-01 00:00:00', ['state' => 'deleted', 'generation' => 2]);
    $tombLive = pgTurn($tomb, 1, '2026-10-01 00:00:00', ['status' => 'pending', 'lease_expires_at' => '2026-11-08 10:00:01']);

    $r = pgRun();

    // The whole session is off limits while the lease holds, its own expired turn included.
    expect($r)->toMatchArray(['turns' => 0, 'sessions' => 0])
        ->and(pgExists($session))->toBeTrue()->and(pgExists($old))->toBeTrue()->and(pgExists($live))->toBeTrue()
        ->and(pgExists($tomb))->toBeTrue()->and(pgExists($tombLive))->toBeTrue();

    test()->travelTo('2026-11-08 10:00:02');                 // lease now expired: normal rules apply
    $again = pgRun();

    expect($again)->toMatchArray(['turns' => 3, 'sessions' => 2])->and(ChatSession::count())->toBe(0);
});

test('running twice is a no-op the second time', function () {
    $session = pgSession('2026-10-01 00:00:00');
    pgTurn($session, 1, '2026-10-01 00:00:00');
    $tomb = pgSession('2026-10-01 00:00:00', ['state' => 'deleted', 'generation' => 2]);
    pgTurn($tomb, 1, '2026-10-01 00:00:00');
    $keep = pgSession(PG_NOW);
    pgTurn($keep, 1, PG_NOW);

    $first = pgRun();
    $second = pgRun();

    expect($first)->toMatchArray(['turns' => 2, 'sessions' => 2])
        ->and($second)->toMatchArray(['turns' => 0, 'sessions' => 0])
        ->and(ChatSession::count())->toBe(1)->and(ChatTurn::count())->toBe(1);
});

test('--dry-run deletes nothing but reports the counts a real run would delete', function () {
    $old = pgSession('2026-10-01 00:00:00');
    pgTurn($old, 1, '2026-10-01 00:00:00');
    $tomb = pgSession('2026-10-01 00:00:00', ['state' => 'deleted', 'generation' => 2]);
    pgTurn($tomb, 1, '2026-10-01 00:00:00');
    $keep = pgSession(PG_NOW);
    pgTurn($keep, 1, PG_NOW);
    $rows = fn () => [ChatSession::count(), ChatTurn::count()];

    $dry = pgRun(dry: true);

    expect($dry)->toMatchArray(['turns' => 2, 'sessions' => 2])
        ->and($dry['output'])->toContain('dry-run')
        ->and($rows())->toBe([3, 3]);

    $real = pgRun();

    expect($real['turns'])->toBe(2)->and($real['sessions'])->toBe(2)->and($rows())->toBe([1, 1]);
});

test('command output carries counts only, never chat content', function () {
    $session = pgSession('2026-10-01 00:00:00');
    pgTurn($session, 1, '2026-10-01 00:00:00', [
        'message' => PG_MARKER, 'response' => ['answer' => PG_MARKER], 'citations' => [['n' => PG_MARKER]],
    ]);
    $tomb = pgSession('2026-10-01 00:00:00', ['state' => 'deleted', 'generation' => 2, 'title' => PG_MARKER]);
    pgTurn($tomb, 1, '2026-10-01 00:00:00', ['request_hash' => PG_MARKER, 'client_turn_id' => '40000000-0000-4000-8000-000000000099']);

    $dry = pgRun(dry: true)['output'];
    $real = pgRun()['output'];

    expect($dry)->not->toContain(PG_MARKER)->and($real)->not->toContain(PG_MARKER)
        ->and($real)->not->toContain('40000000-0000-4000')
        ->and(DB::table('chat_turns')->where('message', PG_MARKER)->exists())->toBeFalse();
});

test('the purge is registered on the scheduler once a day without overlapping', function () {
    app(Kernel::class)->bootstrap();
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($e) => str_contains((string) $e->command, 'ai:purge-chats'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 0 * * *')
        ->and($events->first()->withoutOverlapping)->toBeTrue();
    expect(Artisan::all())->toHaveKey('inspire');
});

test('a late finalize or fail after the purge removed the turn and tombstone writes nothing', function () {
    $session = ctSession();
    $turn = ctReserve($session)->turn;
    app(SessionEraser::class)->erase($session);
    test()->travelTo('2026-11-09 10:01:00');                  // past the 86435 s horizon since the delete at 10:00:00

    pgRun();

    expect(ChatSession::count())->toBe(0)->and(ChatTurn::count())->toBe(0);
    foreach ([$turn, $turn->id] as $handle) {
        expect(ctGate()->finalize($handle, ctResponse(), [], []))->toBeFalse()
            ->and(ctGate()->fail($handle, 'AI_TIMEOUT'))->toBeFalse();
    }
    expect(ChatSession::count())->toBe(0)->and(ChatTurn::count())->toBe(0);
});

test('with a chunk size of 2 every eligible row in every scope is deleted, nothing younger, and a rerun is a no-op', function () {
    PurgeChats::$chunkSize = 2;
    $keepers = [];
    $expiredHost = pgSession(PG_NOW);                          // 5 expired turns, plus one fresh turn that must stay
    foreach (range(1, 5) as $i) {
        pgTurn($expiredHost, $i, '2026-10-01 00:00:00');
    }
    $keepers[] = pgTurn($expiredHost, 6, PG_NOW);
    $emptied = collect(range(1, 5))->map(fn () => pgSession('2026-10-01 00:00:00'));          // 5 old empty active sessions
    $keepers[] = pgSession('2026-11-07 00:00:00');             // young empty active session
    $tombs = collect(range(1, 5))->map(function ($i) {         // 5 expired tombstones with one turn each
        $t = pgSession('2026-10-01 00:00:00', ['state' => 'deleted', 'generation' => 2]);
        pgTurn($t, 1, '2026-11-07 09:00:00');

        return $t;
    });
    $keepers[] = pgSession('2026-11-07 09:59:30', ['state' => 'deleted', 'generation' => 2]);   // inside the horizon

    $first = pgRun();
    $second = pgRun();

    expect($first)->toMatchArray(['turns' => 10, 'sessions' => 10])
        ->and($second)->toMatchArray(['turns' => 0, 'sessions' => 0])
        ->and(ChatSession::count())->toBe(3)->and(ChatTurn::count())->toBe(1)
        ->and(collect($keepers)->every(fn ($row) => pgExists($row)))->toBeTrue()
        ->and($emptied->contains(fn ($s) => pgExists($s)))->toBeFalse()
        ->and($tombs->contains(fn ($s) => pgExists($s)))->toBeFalse();
});
