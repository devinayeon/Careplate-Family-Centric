<?php

use App\Models\ChatSession;
use App\Models\User;
use Database\Seeders\AiPermissionSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

// SYNTHETIC users, foods and provider replies only. Shared by the POST /ai/v1/chat/sessions/{session}/turns tests.

require_once __DIR__.'/GeminiTestSupport.php';
require_once __DIR__.'/ChatTurnSupport.php';

const CHAT_EDU = 'Perhatikan takaran saji saat membandingkan label. Ini edukasi umum.';

function chatSetUp(): void
{
    geminiConfigure();
    test()->seed(AiPermissionSeeder::class);
}

/** Replace the HTTP factory so earlier stubs cannot win, then fake the Gemini endpoint. */
function chatFake(mixed $response = null): void
{
    Http::swap(new Factory);
    Http::fake([GEMINI_URL => $response ?? Http::response(geminiOk(['kind' => 'educational', 'text' => CHAT_EDU]))]);
}

function chatNarration(string $text, string $kind = 'educational'): mixed
{
    return Http::response(geminiOk(['kind' => $kind, 'text' => $text]));
}

/** @return array{0: User, 1: ChatSession} */
function chatActor(string $role = 'family_head'): array
{
    $user = ctUser($role);

    return [$user, ctSession($user)];
}

/** @param array<string, mixed> $extra */
function chatBody(string $message, int $key = 1, array $extra = []): array
{
    return ['message' => $message, 'client_turn_id' => ctKey($key)] + $extra;
}

/** @param array<string, mixed> $body */
function chatSend(User $user, ChatSession $session, array $body): TestResponse
{
    return test()->actingAs($user)->postJson("/ai/v1/chat/sessions/{$session->id}/turns", $body);
}

function chatAsk(User $user, ChatSession $session, string $message, int $key = 1, array $extra = []): TestResponse
{
    return chatSend($user, $session, chatBody($message, $key, $extra));
}

function chatError(TestResponse $response, int $status, string $code, bool $retryable = false): void
{
    $response->assertStatus($status)->assertJsonPath('error.code', $code)->assertJsonPath('error.retryable', $retryable);
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and(array_keys($response->json()))->toEqualCanonicalizing(['error', 'request_id']);
}

/** Asserts the 200 body is a contract ChatResponse (ids included) and returns it. */
function chatOk(TestResponse $response): array
{
    $response->assertOk();
    $json = $response->json();
    expect(array_keys($json))->toBe([
        'schema_version', 'request_id', 'session_id', 'turn_id', 'client_turn_id', 'sequence', 'mode',
        'retrieval_status', 'answer', 'claims', 'citations', 'general_knowledge_notice', 'warnings',
    ])->and($json['schema_version'])->toBe('1.0')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($json['request_id'])->toBe($response->headers->get('X-Request-Id'));
    cfAssertChatShape(array_diff_key($json, array_flip(['schema_version', 'request_id', 'session_id', 'turn_id', 'client_turn_id', 'sequence'])));

    return $json;
}

/** Number of provider calls recorded by the HTTP fake. */
function chatProviderCalls(): int
{
    return Http::recorded()->count();
}
