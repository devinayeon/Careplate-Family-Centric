<?php

use App\Exceptions\Ai\AiException;
use App\Models\ChatSession;
use App\Models\ChatTurn;
use App\Models\Food;
use App\Services\Ai\Chat\SessionEraser;
use App\Services\Ai\Foods\FoodMatch;
use App\Services\Ai\Foods\FoodSearch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

require_once __DIR__.'/ChatApiSupport.php';

beforeEach(function () {
    chatSetUp();
    chatFake();
});

test('replaying a completed key returns the stored response and makes no provider call', function () {
    cfFood(['name' => 'Tahu contoh', 'protein_g' => '10']);
    [$user, $session] = chatActor();

    $first = chatAsk($user, $session, 'Apa manfaat protein pada tahu contoh?', 1)->assertOk()->json();
    $second = chatAsk($user, $session, '  Apa manfaat protein pada tahu contoh?  ', 1)->assertOk()->json();

    expect($second)->toBe($first)->and(chatProviderCalls())->toBe(1)->and(ChatTurn::query()->count())->toBe(1);
});

test('the same key with a different message, food or amount is IDEMPOTENCY_CONFLICT', function (array $second) {
    $food = cfFood(['name' => 'Tahu contoh']);
    [$user, $session] = chatActor();
    chatAsk($user, $session, 'Berapa protein tahu contoh?', 1)->assertOk();

    chatError(chatSend($user, $session, array_merge(chatBody('Berapa protein tahu contoh?', 1), $second)), 409, 'IDEMPOTENCY_CONFLICT');
    chatError(chatSend($user, $session, chatBody('Berapa lemak tahu contoh?', 1) + ['food_id' => $food->id]), 409, 'IDEMPOTENCY_CONFLICT');
})->with([
    'amount' => [['amount_g' => 50]],
    'food' => [['food_id' => '10000000-0000-4000-8000-000000000001']],
]);

test('equal amounts in different spellings share a hash; the hash is a keyed HMAC, not a plain sha256', function () {
    cfFood(['name' => 'Tahu contoh']);
    [$user, $session] = chatActor();
    $message = 'Berapa protein tahu contoh?';

    chatAsk($user, $session, $message, 1, ['amount_g' => 50])->assertOk();
    chatAsk($user, $session, $message, 1, ['amount_g' => '50.00'])->assertOk();
    chatAsk($user, $session, $message, 1, ['amount_g' => 50.0])->assertOk();

    $hash = ChatTurn::query()->first()->request_hash;
    expect(ChatTurn::query()->count())->toBe(1)->and($hash)->toMatch('/^[0-9a-f]{64}$/')
        ->and($hash)->not->toBe(hash('sha256', $message))
        ->and($hash)->not->toBe(hash('sha256', json_encode(['message' => $message, 'food_id' => null, 'amount_g' => '50'])));
    config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
    chatError(chatAsk($user, $session, $message, 1, ['amount_g' => 50]), 409, 'IDEMPOTENCY_CONFLICT');
});

test('the follow-up kalau 100 gram re-fetches the CURRENT food, never numbers from history', function () {
    $food = cfFood(['name' => 'Tahu contoh', 'protein_g' => '10', 'current_revision' => 3]);
    [$user, $session] = chatActor();

    $first = chatOk(chatAsk($user, $session, 'Berapa protein 50 gram tahu contoh?', 1));
    Food::query()->whereKey($food->id)->update(['protein_g' => '20', 'current_revision' => 4]);
    $second = chatOk(chatAsk($user, $session, 'kalau 100 gram?', 2));

    expect($first['claims'][0]['derived_value'])->toBe(5)
        ->and($second['mode'])->toBe('database')
        ->and($second['claims'])->toHaveCount(6)
        ->and(collect($second['claims'])->firstWhere('nutrient', 'protein_g')['derived_value'])->toBe(20)
        ->and($second['citations'][0]['revision'])->toBe(4)
        ->and($second['claims'][0]['requested_amount'])->toBe(100);
    Http::assertNothingSent();
});

test('the follow-up entity is carried only when exactly one food was identified last turn', function () {
    cfFood(['name' => 'Nasi putih', 'aliases' => ['nasi', 'nasi putih']]);
    cfFood(['name' => 'Nasi merah', 'aliases' => ['nasi', 'nasi merah']]);
    [$user, $session] = chatActor();

    chatOk(chatAsk($user, $session, 'Berapa kalori nasi?', 1));
    $next = chatOk(chatAsk($user, $session, 'kalau 100 gram?', 2));

    expect($next['mode'])->toBe('clarification')->and($next['claims'])->toBe([]);
});

test('the follow-up never reads another session and a revoked remembered food is not answered', function () {
    $food = cfFood(['name' => 'Tahu contoh']);
    [$user, $one] = chatActor();
    $two = ctSession($user);

    chatAsk($user, $one, 'Berapa protein tahu contoh?', 1)->assertOk();
    $other = chatOk(chatAsk($user, $two, 'kalau 100 gram?', 2));
    Food::query()->whereKey($food->id)->update(['status' => 'revoked', 'current_revision' => 4]);
    $revoked = chatOk(chatAsk($user, $one, 'kalau 100 gram?', 3));

    expect($other['mode'])->toBe('clarification')->and($revoked['mode'])->toBe('clarification')->and($revoked['claims'])->toBe([]);
});

test('the provider prompt carries only this session history, with numbers redacted, inside a data envelope', function () {
    $food = cfFood(['name' => 'Tahu contoh', 'protein_g' => '10']);
    [$user, $session] = chatActor();
    $foreign = ctSession(ctUser());
    ctTurn($foreign, 1, ['message' => 'RAHASIA-SESI-LAIN']);

    chatAsk($user, $session, 'Berapa protein 50 gram tahu contoh?', 1)->assertOk();
    chatAsk($user, $session, 'Apa fungsi protein?', 2)->assertOk();

    Http::assertSent(function (Request $request) use ($food) {
        $text = $request->data()['contents'][0]['parts'][0]['text'];

        return str_contains($text, 'Berapa protein 50 gram tahu contoh?')
            && ! str_contains($text, 'RAHASIA-SESI-LAIN')
            && ! str_contains($text, '5 g protein')
            && ! str_contains($text, $food->id)
            && is_array(json_decode(substr($text, (int) strpos($text, '{')), true));
    });
});

test('only the configured number of completed turns is sent and the oldest pairs go first over budget', function () {
    [$user, $session] = chatActor();
    foreach (range(1, 8) as $n) {
        ctTurn($session, $n, ['message' => "Pertanyaan nomor-$n", 'response' => ctResponse('general')]);
    }
    ctTurn($session, 9, ['message' => 'GAGAL-JANGAN-DIKIRIM', 'status' => 'failed', 'response' => null]);
    $session->update(['next_sequence' => 10]);

    chatAsk($user, $session, 'Apa fungsi protein?', 1)->assertOk();

    Http::assertSent(function (Request $request) {
        $text = $request->data()['contents'][0]['parts'][0]['text'];

        return str_contains($text, 'nomor-8') && str_contains($text, 'nomor-3')
            && ! str_contains($text, 'nomor-2') && ! str_contains($text, 'GAGAL');
    });

    chatFake();
    config(['ai.context.text_token_budget' => 90]);
    chatAsk($user, $session, 'Apa fungsi protein?', 2)->assertOk();
    Http::assertSent(function (Request $request) {
        $text = $request->data()['contents'][0]['parts'][0]['text'];

        return str_contains($text, 'nomor-8') && ! str_contains($text, 'nomor-4');
    });
});

test('revoking a cited food between reserve and finalize blocks completion with KNOWLEDGE_CHANGED', function () {
    $food = cfFood(['name' => 'Tahu contoh', 'protein_g' => '10']);
    [$user, $session] = chatActor();
    chatFake(function () use ($food) {
        Food::query()->whereKey($food->id)->update(['status' => 'revoked', 'current_revision' => 4]);

        return chatNarration(CHAT_EDU);
    });

    chatError(chatAsk($user, $session, 'Apa manfaat protein pada tahu contoh?'), 409, 'KNOWLEDGE_CHANGED', true);

    $turn = ChatTurn::query()->first();
    expect($turn->status)->toBe('failed')->and($turn->error_code)->toBe('KNOWLEDGE_CHANGED')->and($turn->response)->toBeNull()
        ->and($session->fresh()->active_turn_id)->toBeNull();
    chatError(chatAsk($user, $session, 'Apa manfaat protein pada tahu contoh?'), 409, 'KNOWLEDGE_CHANGED', true);
});

test('a session deleted during a slow provider call blocks the late result and writes nothing', function () {
    [$user, $session] = chatActor();
    chatFake(function () use ($session) {
        app(SessionEraser::class)->erase($session);

        return chatNarration(CHAT_EDU);
    });

    chatError(chatAsk($user, $session, 'Apa fungsi protein?'), 404, 'NOT_FOUND');

    $turn = ChatTurn::query()->first();
    expect($turn->status)->toBe('pending')->and($turn->response)->toBeNull()->and($turn->message)->toBeNull()
        ->and($session->fresh()->state)->toBe('deleted')->and($session->fresh()->active_turn_id)->toBeNull();
    chatError(chatAsk($user, $session, 'Apa fungsi protein?'), 404, 'NOT_FOUND');
});

test('a lease that expired during the provider call ends as a stored AI_TIMEOUT, not a completed answer', function () {
    [$user, $session] = chatActor();
    chatFake(function () {
        DB::table('chat_turns')->update(['lease_expires_at' => now()->subSecond()]);

        return chatNarration(CHAT_EDU);
    });

    chatError(chatAsk($user, $session, 'Apa fungsi protein?'), 504, 'AI_TIMEOUT', true);

    expect(ChatTurn::query()->first()->status)->toBe('failed')->and(ChatTurn::query()->first()->error_code)->toBe('AI_TIMEOUT')
        ->and($session->fresh()->active_turn_id)->toBeNull();
});

test('a busy session rejects a second turn with SESSION_BUSY before any provider call', function () {
    [$user, $session] = chatActor();
    ctReserve($session, 7);

    chatError(chatAsk($user, $session, 'Apa fungsi protein?', 8), 409, 'SESSION_BUSY', true);
    chatError(chatAsk($user, $session, 'Berapa protein?', 7), 409, 'IDEMPOTENCY_CONFLICT');

    Http::assertNothingSent();
});

test('a failed lookup in the database is 503 KNOWLEDGE_UNAVAILABLE stored for the key, never no_match', function (Closure $break) {
    cfFood(['name' => 'Tahu contoh']);
    [$user, $session] = chatActor();
    $break();

    chatError(chatAsk($user, $session, 'Berapa protein tahu contoh?'), 503, 'KNOWLEDGE_UNAVAILABLE', true);

    expect(ChatTurn::query()->first()->status)->toBe('failed')->and(ChatTurn::query()->first()->error_code)->toBe('KNOWLEDGE_UNAVAILABLE')
        ->and($session->fresh()->active_turn_id)->toBeNull();
    chatError(chatAsk($user, $session, 'Berapa protein tahu contoh?'), 503, 'KNOWLEDGE_UNAVAILABLE', true);
    Http::assertNothingSent();
})->with([
    'foods table gone' => [function () {
        Schema::disableForeignKeyConstraints();
        Schema::drop('foods');
    }],
    'search throws' => [function () {
        app()->instance(FoodSearch::class, new class extends FoodSearch
        {
            public function resolve(array $phrases): FoodMatch
            {
                throw AiException::for('KNOWLEDGE_UNAVAILABLE');
            }
        });
    }],
]);

test('an unexpected failure becomes a stored INTERNAL envelope without the message text', function () {
    [$user, $session] = chatActor();
    app()->instance(FoodSearch::class, new class extends FoodSearch
    {
        public function resolve(array $phrases): FoodMatch
        {
            throw new RuntimeException('SQL with TEXT-CANARY');
        }
    });

    $response = chatAsk($user, $session, 'Berapa protein tahu contoh?');

    chatError($response, 500, 'INTERNAL');
    expect($response->getContent())->not->toContain('CANARY')->and(ChatTurn::query()->first()->error_code)->toBe('INTERNAL');
});

test('quota is spent before any provider call: the 429 arrives with nothing sent', function () {
    config(['ai.quotas.chat.per_minute' => 2, 'ai.quotas.chat.per_day' => 50]);
    [$user, $session] = chatActor();

    chatAsk($user, $session, 'Berapa dosis insulin?', 1)->assertOk();
    chatAsk($user, $session, 'Berapa dosis insulin?', 2)->assertOk();
    $limited = chatAsk($user, $session, 'Apa fungsi protein?', 3);

    chatError($limited, 429, 'RATE_LIMITED', true);
    expect((int) $limited->headers->get('Retry-After'))->toBeGreaterThan(0)->and(ChatTurn::query()->count())->toBe(2);
    Http::assertNothingSent();
});

test('chat text appears nowhere in the logs', function () {
    cfFood(['name' => 'Tahu contoh']);
    [$user, $session] = chatActor();
    [$logged] = captureLogs();
    $canary = 'CANARY-PESAN-RAHASIA-5521';

    chatAsk($user, $session, "Apa fungsi protein? $canary", 1)->assertOk();
    chatFake(chatNarration('Roti punya 12 g gula '.$canary));
    chatAsk($user, $session, "Apa fungsi gula? $canary", 2);
    chatFake(fn () => throw new ConnectionException("cURL error 28 $canary"));
    chatAsk($user, $session, "Apa fungsi lemak? $canary", 3);
    chatAsk($user, $session, "Berapa protein tahu contoh? $canary", 4);

    $lines = implode("\n", $logged->getArrayCopy());
    expect($lines)->not->toContain($canary)->and($lines)->not->toContain('CANARY');
    expect(collect($logged)->contains(fn ($l) => str_contains($l, 'ai.chat')))->toBeTrue();
});

test('the session row is untouched by a foreign attempt', function () {
    [$owner, $session] = chatActor();

    chatSend(ctUser('admin'), $session, chatBody('halo'))->assertNotFound();

    expect(ChatSession::query()->find($session->id)->next_sequence)->toBe(1);
});
