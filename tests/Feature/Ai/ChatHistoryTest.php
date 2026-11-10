<?php

use App\Services\Ai\Chat\HistoryCursor;
use Database\Seeders\AiPermissionSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

require_once __DIR__.'/ChatTurnSupport.php';

beforeEach(function () {
    test()->seed(AiPermissionSeeder::class);
    test()->travelTo('2026-11-08 10:00:00');
    Http::fake();
});

afterEach(fn () => Http::assertNothingSent());

function chTurns($user, $session, string $query = ''): TestResponse
{
    return test()->actingAs($user)->getJson("/ai/v1/chat/sessions/{$session->id}/turns".$query);
}

test('history lists the owner turns by sequence with the Turn shape and visible pending/failed states', function () {
    $user = ctUser();
    $session = ctSession($user);
    $done = ctReserve($session, 1, 'h1', 'Halo pertama');
    ctGate()->finalize($done->turn, ctResponse('general'), [], []);
    ctGate()->fail(ctReserve($session, 2, 'h2', 'Halo kedua')->turn, 'AI_PROVIDER_ERROR');
    $pending = ctReserve($session, 3, 'h3', 'Halo ketiga')->turn;

    $r = chTurns($user, $session)->assertOk();
    $data = $r->json('data');

    expect($r->headers->get('Cache-Control'))->toContain('no-store')
        ->and(array_keys($r->json()))->toBe(['data', 'next_cursor'])
        ->and($r->json('next_cursor'))->toBeNull()
        ->and(array_column($data, 'sequence'))->toBe([1, 2, 3])
        ->and(array_keys($data[0]))->toBe(['id', 'client_turn_id', 'sequence', 'state', 'user_content', 'response', 'failure_code', 'created_at'])
        ->and($data[0])->toMatchArray(['id' => $done->turn->id, 'client_turn_id' => ctKey(1), 'state' => 'completed',
            'user_content' => 'Halo pertama', 'response' => ctResponse('general'), 'failure_code' => null,
            'created_at' => '2026-11-08T10:00:00Z'])
        ->and($data[1])->toMatchArray(['state' => 'failed', 'user_content' => 'Halo kedua', 'response' => null, 'failure_code' => 'AI_PROVIDER_ERROR'])
        ->and($data[2])->toMatchArray(['id' => $pending->id, 'state' => 'pending', 'response' => null, 'failure_code' => null])
        ->and($r->getContent())->not->toContain('token')->not->toContain('request_hash')->not->toContain($pending->token);
});

test('history pages with an opaque cursor and limit 1 to 20', function () {
    $user = ctUser();
    $session = ctSession($user);
    foreach (range(1, 5) as $i) {
        ctTurn($session, $i);
    }

    $p1 = chTurns($user, $session, '?limit=2')->assertOk()->json();
    $p2 = chTurns($user, $session, '?limit=2&cursor='.urlencode($p1['next_cursor']))->json();
    $p3 = chTurns($user, $session, '?limit=2&cursor='.urlencode($p2['next_cursor']))->json();

    expect(array_column($p1['data'], 'sequence'))->toBe([1, 2])
        ->and(array_column($p2['data'], 'sequence'))->toBe([3, 4])
        ->and(array_column($p3['data'], 'sequence'))->toBe([5])
        ->and($p3['next_cursor'])->toBeNull()
        ->and($p1['next_cursor'])->not->toContain('sequence')->not->toContain($session->id)
        ->and(chTurns($user, $session, '?limit=20')->json('data'))->toHaveCount(5);
    foreach (['0', '21', 'abc'] as $bad) {
        chTurns($user, $session, '?limit='.$bad)->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');
    }
});

test('a cursor from another session, another owner, a session list or a forged payload is INVALID_INPUT', function () {
    $user = ctUser();
    $a = ctSession($user);
    $b = ctSession($user);
    $other = ctUser();
    $foreignSession = ctSession($other);
    foreach ([$a, $b, $foreignSession] as $s) {
        foreach (range(1, 3) as $i) {
            ctTurn($s, $i);
        }
    }
    $fromB = chTurns($user, $b, '?limit=1')->json('next_cursor');
    $fromForeign = chTurns($other, $foreignSession, '?limit=1')->json('next_cursor');
    $fromList = test()->actingAs($user)->getJson('/ai/v1/chat/sessions?limit=1')->json('next_cursor');
    $forged = rtrim(strtr(base64_encode(json_encode(['v' => 1, 'k' => 'turns', 's' => 'x', 'p' => [0]])), '+/', '-_'), '=');

    foreach ([$fromB, $fromForeign, $fromList, $forged, 'not base64 !!', base64_encode('[]'), base64_encode('"ORDER BY 1"')] as $cursor) {
        chTurns($user, $a, '?cursor='.urlencode($cursor))->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');
    }
    chTurns($user, $a, '?cursor[]=x')->assertStatus(422);
    expect(chTurns($user, $a, '?order=desc&user_id='.$other->id)->json('data'))->toHaveCount(3);
});

test('turns older than the retention window are gone from history before any purge job', function () {
    $user = ctUser();
    $session = ctSession($user);
    ctTurn($session, 1, ['message' => 'Pesan lama sekali']);
    test()->travelTo('2026-11-14 09:00:00');
    ctTurn($session, 2, ['message' => 'Pesan baru']);

    test()->travelTo('2026-11-15 10:00:01');
    $data = chTurns($user, $session)->assertOk()->json('data');

    expect(array_column($data, 'sequence'))->toBe([2])
        ->and(json_encode($data))->not->toContain('lama sekali');

    test()->travelTo('2026-11-21 09:00:01');
    expect(chTurns($user, $session)->json('data'))->toBe([]);
});

test('the history cursor round-trips and is bound to its scope', function () {
    $cursor = HistoryCursor::encode('turns', 'scope-a', [3]);

    expect(HistoryCursor::decode($cursor, 'turns', 'scope-a'))->toBe([3])
        ->and(cfThrown(fn () => HistoryCursor::decode($cursor, 'turns', 'scope-b'))->errorCode)->toBe('INVALID_INPUT')
        ->and(cfThrown(fn () => HistoryCursor::decode($cursor, 'sessions', 'scope-a'))->errorCode)->toBe('INVALID_INPUT')
        ->and(HistoryCursor::decode(null, 'turns', 'scope-a'))->toBeNull();
});
