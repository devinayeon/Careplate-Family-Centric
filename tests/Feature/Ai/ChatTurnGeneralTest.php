<?php

use App\Models\ChatTurn;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/ChatApiSupport.php';

beforeEach(fn () => chatSetUp());

test('general mode makes exactly one provider call with the chat prompt, schema and caps', function () {
    chatFake();
    [$user, $session] = chatActor();

    $json = chatOk(chatAsk($user, $session, 'Apa fungsi protein?'));

    expect($json['mode'])->toBe('general')->and($json['retrieval_status'])->toBe('not_needed')
        ->and($json['answer'])->toBe(CHAT_EDU)->and($json['claims'])->toBe([])->and($json['citations'])->toBe([])
        ->and($json['general_knowledge_notice'])->toContain('bukan hasil database');
    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $body['systemInstruction']['parts'][0]['text'] === file_get_contents(base_path('ai/prompts/chat-system.txt'))
            && $body['generationConfig']['responseFormat']['text']['schema'] === json_decode(file_get_contents(base_path('ai/schemas/provider-chat.json')), true)
            && $body['generationConfig']['maxOutputTokens'] === config('ai.chat.max_output_tokens')
            && $body['store'] === false
            && str_contains($body['contents'][0]['parts'][0]['text'], 'Apa fungsi protein?');
    });
});

test('mixed mode keeps the server numbers and citation and appends the narration', function () {
    chatFake();
    $food = cfFood(['name' => 'Tahu contoh', 'protein_g' => '10']);
    [$user, $session] = chatActor();

    $json = chatOk(chatAsk($user, $session, 'Apa manfaat protein pada tahu contoh?'));

    expect($json['mode'])->toBe('mixed')->and($json['retrieval_status'])->toBe('found')
        ->and($json['claims'][0]['derived_value'])->toBe(10)->and($json['citations'][0]['food_id'])->toBe($food->id)
        ->and($json['answer'])->toContain('100 g')->and($json['answer'])->toEndWith(CHAT_EDU)
        ->and($json['general_knowledge_notice'])->toBeString()->not->toBe('');
    Http::assertSentCount(1);
});

test('narration with nutrition numbers, links or foreign sources falls back to the server template', function (string $text) {
    chatFake(chatNarration($text));
    cfFood(['name' => 'Tahu contoh', 'protein_g' => '10']);
    [$user, $session] = chatActor();

    $json = chatOk(chatAsk($user, $session, 'Apa manfaat protein pada tahu contoh?'));

    expect($json['mode'])->toBe('database')->and($json['answer'])->not->toContain($text)
        ->and($json['claims'][0]['derived_value'])->toBe(10)
        ->and(collect($json['warnings'])->implode(' '))->toContain('Penjelasan umum');
    Http::assertSentCount(1);
})->with([
    'grams' => 'Tahu mengandung 8 g protein per 100 g.',
    'decimal comma mg' => 'Natrium sekitar 7,5 mg saja.',
    'kcal' => 'Sekitar 150 kcal.',
    'percent' => 'Memenuhi 20% kebutuhan.',
    'spelled' => 'Kira-kira lima puluh gram sudah cukup.',
    'link' => 'Baca di https://contoh.example/gizi untuk detail.',
    'bare domain' => 'Lihat gizi.example.com untuk detail.',
    'source name' => 'Menurut WHO dan Kemenkes, protein penting.',
    'fake citation' => 'Protein penting [1] bagi tubuh.',
]);

test('general narration that breaks the guard is AI_OUTPUT_INVALID, stored for the key, with one call only', function () {
    chatFake(chatNarration('Roti punya 12 g gula.'));
    [$user, $session] = chatActor();

    chatError(chatAsk($user, $session, 'Apa fungsi gula?'), 502, 'AI_OUTPUT_INVALID', true);
    chatError(chatAsk($user, $session, 'Apa fungsi gula?'), 502, 'AI_OUTPUT_INVALID', true);

    Http::assertSentCount(1);
    expect(ChatTurn::query()->first()->status)->toBe('failed')->and(ChatTurn::query()->first()->error_code)->toBe('AI_OUTPUT_INVALID');
});

test('model JSON with extra, missing or mistyped keys is AI_OUTPUT_INVALID', function (array $payload) {
    chatFake(Http::response(geminiOk($payload)));
    [$user, $session] = chatActor();

    chatError(chatAsk($user, $session, 'Apa fungsi protein?'), 502, 'AI_OUTPUT_INVALID', true);

    expect(ChatTurn::query()->first()->error_code)->toBe('AI_OUTPUT_INVALID');
    Http::assertSentCount(1);
})->with([
    'extra key' => [['kind' => 'educational', 'text' => 'Protein membangun otot.', 'claims' => []]],
    'missing text' => [['kind' => 'educational']],
    'missing kind' => [['text' => 'Protein membangun otot.']],
    'unknown kind' => [['kind' => 'database', 'text' => 'Protein membangun otot.']],
    'text not string' => [['kind' => 'educational', 'text' => ['a']]],
    'empty text' => [['kind' => 'educational', 'text' => '  ']],
    'a list' => [['educational', 'Protein membangun otot.']],
    'empty array' => [[]],
]);

test('a referral from the model on a general question is a referral without claims', function () {
    chatFake(chatNarration('Konsultasikan hal ini dengan dokter atau ahli gizi Anda.', 'referral'));
    [$user, $session] = chatActor();

    $json = chatOk(chatAsk($user, $session, 'Apa fungsi protein?'));

    expect($json['mode'])->toBe('referral')->and($json['retrieval_status'])->toBe('not_needed')->and($json['claims'])->toBe([]);
});

test('provider failures on a general question are stored as the key terminal error and replayed without a call', function (mixed $reply, int $status, string $code, bool $retryable) {
    chatFake($reply);
    [$user, $session] = chatActor();

    chatError(chatAsk($user, $session, 'Apa fungsi protein?'), $status, $code, $retryable);
    $calls = chatProviderCalls();
    chatError(chatAsk($user, $session, 'Apa fungsi protein?'), $status, $code, $retryable);

    expect(chatProviderCalls())->toBe($calls)
        ->and(ChatTurn::query()->first()->status)->toBe('failed')->and(ChatTurn::query()->first()->error_code)->toBe($code)
        ->and($session->fresh()->active_turn_id)->toBeNull();
    // A new key is a new attempt and may succeed later.
    chatFake();
    expect(chatAsk($user, $session, 'Apa fungsi protein?', 2)->status())->toBe(200);
})->with([
    'provider 500' => [fn () => Http::response('x', 500), 502, 'AI_PROVIDER_ERROR', true],
    'provider 400' => [fn () => Http::response('x', 400), 502, 'AI_PROVIDER_ERROR', true],
    'blocked output' => [fn () => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']]), 502, 'AI_OUTPUT_INVALID', true],
    'timeout' => [fn () => throw new ConnectionException('cURL error 28: Operation timed out'), 504, 'AI_TIMEOUT', true],
]);

test('with the provider disabled a general question is AI_DISABLED but a database question still answers', function () {
    chatFake();
    config(['ai.enabled' => false]);
    cfFood(['name' => 'Tahu contoh', 'protein_g' => '10']);
    [$user, $session] = chatActor();

    chatError(chatAsk($user, $session, 'Apa fungsi protein?', 1), 503, 'AI_DISABLED');
    $db = chatOk(chatAsk($user, $session, 'Berapa protein tahu contoh?', 2));
    $mixed = chatOk(chatAsk($user, $session, 'Apa manfaat protein pada tahu contoh?', 3));

    expect($db['mode'])->toBe('database')->and($db['claims'][0]['derived_value'])->toBe(10)
        ->and($mixed['mode'])->toBe('database')->and($mixed['claims'][0]['derived_value'])->toBe(10);
    Http::assertNothingSent();
});

test('a provider outage leaves database facts available on a mixed question', function () {
    chatFake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));
    cfFood(['name' => 'Tahu contoh', 'protein_g' => '10']);
    [$user, $session] = chatActor();

    $json = chatOk(chatAsk($user, $session, 'Apa manfaat protein pada tahu contoh?'));

    expect($json['mode'])->toBe('database')->and($json['claims'])->toHaveCount(1)
        ->and(ChatTurn::query()->first()->status)->toBe('completed');
});
