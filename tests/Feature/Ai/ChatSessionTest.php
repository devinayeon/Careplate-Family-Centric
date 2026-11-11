<?php

use App\Models\ChatSession;
use App\Models\ChatTurn;
use Database\Seeders\AiPermissionSeeder;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/ChatTurnSupport.php';

beforeEach(function () {
    test()->seed(AiPermissionSeeder::class);
    test()->travelTo('2026-11-08 10:00:00');
    Http::fake();
});

afterEach(fn () => Http::assertNothingSent());

test('POST creates an owned active session: 201 Session shape, no-store', function () {
    $user = ctUser();

    $r = test()->actingAs($user)->postJson('/ai/v1/chat/sessions', [])->assertStatus(201);
    $session = ChatSession::sole();

    expect(array_keys($r->json()))->toBe(['id', 'state', 'title', 'created_at', 'updated_at'])
        ->and($r->json('id'))->toBe($session->id)
        ->and($r->json('state'))->toBe('active')
        ->and($r->json('title'))->toBeNull()
        ->and($r->json('created_at'))->toBe('2026-11-08T10:00:00Z')
        ->and($r->headers->get('Cache-Control'))->toContain('no-store')
        ->and($session->user_id)->toBe($user->id)
        ->and($session->generation)->toBe(1)
        ->and($session->next_sequence)->toBe(1);
});

test('POST rejects any body field, including a forged owner, with INVALID_INPUT', function () {
    $user = ctUser();
    $other = ctUser();

    foreach ([['user_id' => $other->id], ['title' => 'x'], ['state' => 'deleted']] as $body) {
        test()->actingAs($user)->postJson('/ai/v1/chat/sessions', $body)
            ->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');
    }
    expect(ChatSession::count())->toBe(0);
});

test('auth and permission: 401 guest, 403 without health_tool:use_ai_chat on every chat route', function () {
    $session = ctSession();
    $noPerm = ctUser('');

    test()->postJson('/ai/v1/chat/sessions', [])->assertStatus(401)->assertJsonPath('error.code', 'UNAUTHENTICATED');
    test()->actingAs($noPerm)->postJson('/ai/v1/chat/sessions', [])->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
    test()->actingAs($noPerm)->getJson('/ai/v1/chat/sessions')->assertStatus(403);
    test()->actingAs($noPerm)->getJson("/ai/v1/chat/sessions/{$session->id}/turns")->assertStatus(403);
    test()->actingAs($noPerm)->deleteJson("/ai/v1/chat/sessions/{$session->id}")->assertStatus(403);
});

test('another account, including an admin, gets 404 for a session it does not own', function (string $role) {
    $session = ctSession(ctUser('family_head'));
    ctTurn($session, 1, ['message' => 'Rahasia pemilik']);
    $intruder = ctUser($role);

    foreach ([
        test()->actingAs($intruder)->getJson("/ai/v1/chat/sessions/{$session->id}/turns"),
        test()->actingAs($intruder)->deleteJson("/ai/v1/chat/sessions/{$session->id}"),
    ] as $r) {
        $r->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
        expect(array_keys($r->json()))->toBe(['error', 'request_id'])
            ->and($r->getContent())->not->toContain('Rahasia');
    }
    expect(ctFresh($session)->state)->toBe('active');
})->with(['family_head', 'nutritionist', 'admin']);

test('unknown and malformed session ids are 404 envelopes', function () {
    $user = ctUser();

    test()->actingAs($user)->getJson('/ai/v1/chat/sessions/10000000-0000-4000-8000-000000000999/turns')->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
    test()->actingAs($user)->deleteJson('/ai/v1/chat/sessions/not-a-uuid')->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
});

test('list shows only the caller active sessions, newest first, with cursor pagination', function () {
    $user = ctUser();
    $other = ctUser();
    $mine = [];
    foreach (range(1, 5) as $i) {
        test()->travelTo("2026-11-08 10:0$i:00");
        $mine[] = ctSession($user)->id;
    }
    ctSession($other);
    ctSession($user, ['state' => 'deleted', 'generation' => 2]);

    $page1 = test()->actingAs($user)->getJson('/ai/v1/chat/sessions?limit=2')->assertOk()->json();
    $page2 = test()->actingAs($user)->getJson('/ai/v1/chat/sessions?limit=2&cursor='.urlencode($page1['next_cursor']))->assertOk()->json();
    $page3 = test()->actingAs($user)->getJson('/ai/v1/chat/sessions?limit=2&cursor='.urlencode($page2['next_cursor']))->assertOk()->json();

    expect(array_keys($page1))->toBe(['data', 'next_cursor'])
        ->and(array_column($page1['data'], 'id'))->toBe([$mine[4], $mine[3]])
        ->and(array_column($page2['data'], 'id'))->toBe([$mine[2], $mine[1]])
        ->and(array_column($page3['data'], 'id'))->toBe([$mine[0]])
        ->and($page3['next_cursor'])->toBeNull()
        ->and(array_keys($page1['data'][0]))->toBe(['id', 'state', 'title', 'created_at', 'updated_at'])
        ->and(test()->actingAs($other)->getJson('/ai/v1/chat/sessions')->json('data'))->toHaveCount(1);
});

test('list limit is 1 to 20 and a cursor of another owner or kind is INVALID_INPUT', function () {
    $user = ctUser();
    $other = ctUser();
    foreach (range(1, 3) as $i) {
        ctSession($user);
        ctSession($other);
    }
    $foreign = test()->actingAs($other)->getJson('/ai/v1/chat/sessions?limit=1')->json('next_cursor');
    $session = ctSession($user);
    foreach (range(1, 2) as $i) {
        ctTurn($session, $i);
    }
    $turnCursor = test()->actingAs($user)->getJson("/ai/v1/chat/sessions/{$session->id}/turns?limit=1")->json('next_cursor');

    foreach (['0', '21', 'x', '-1'] as $bad) {
        test()->actingAs($user)->getJson('/ai/v1/chat/sessions?limit='.$bad)->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');
    }
    test()->actingAs($user)->getJson('/ai/v1/chat/sessions?limit=20')->assertOk();
    foreach ([$foreign, $turnCursor, 'garbage', base64_encode('{"k":"x"}'), str_repeat('a', 501)] as $cursor) {
        test()->actingAs($user)->getJson('/ai/v1/chat/sessions?cursor='.urlencode($cursor))
            ->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');
    }
    test()->actingAs($user)->getJson('/ai/v1/chat/sessions?user_id='.$other->id)->assertOk()
        ->assertJsonCount(4, 'data');
});

test('DELETE is 204, bumps generation, clears the slot and erases turn content in place', function () {
    $food = cfFood();
    $user = ctUser();
    $session = ctSession($user, ['last_food_id' => $food->id, 'next_sequence' => 2]);
    ctTurn($session, 1, ['message' => 'Pesan pribadi', 'citations' => [ctCitation($food)], 'food_refs' => [ctFoodRef($food)], 'food_id' => $food->id, 'amount_g' => '50']);
    $pending = ctReserve(ctFresh($session), 2)->turn;

    $r = test()->actingAs($user)->deleteJson("/ai/v1/chat/sessions/{$session->id}")->assertNoContent();
    $s = ctFresh($session);

    expect($r->getContent())->toBe('')
        ->and($r->headers->get('Cache-Control'))->toContain('no-store')
        ->and($s->state)->toBe('deleted')->and($s->generation)->toBe(2)
        ->and($s->active_turn_id)->toBeNull()->and($s->active_token)->toBeNull()
        ->and($s->last_food_id)->toBeNull()
        ->and(ChatTurn::where('session_id', $session->id)->count())->toBe(2);
    foreach (ChatTurn::where('session_id', $session->id)->get() as $turn) {
        expect($turn->message)->toBeNull()->and($turn->response)->toBeNull()->and($turn->citations)->toBeNull()
            ->and($turn->food_refs)->toBeNull()->and($turn->food_id)->toBeNull()->and($turn->amount_g)->toBeNull();
    }
    // A late provider result for the pending turn is fenced off.
    expect(ctGate()->finalize($pending, ctResponse('general'), [], []))->toBeFalse()
        ->and(ctFresh($pending)->response)->toBeNull();
    test()->actingAs($user)->getJson("/ai/v1/chat/sessions/{$session->id}/turns")->assertStatus(404);
    test()->actingAs($user)->deleteJson("/ai/v1/chat/sessions/{$session->id}")->assertStatus(404);
    expect(test()->actingAs($user)->getJson('/ai/v1/chat/sessions')->json('data'))->toBe([]);
});
