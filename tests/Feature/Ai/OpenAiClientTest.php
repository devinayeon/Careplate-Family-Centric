<?php

use App\Exceptions\Ai\AiException;
use App\Services\Ai\Gemini\GeminiClient;
use App\Services\Ai\Gemini\GeminiResult;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

const OAI_KEY = 'test-openai-key-123';

function oaiConfigure(): void
{
    config([
        'ai.enabled' => true,
        'ai.provider' => 'openai',
        'ai.openai.api_key' => OAI_KEY,
        'ai.openai.model' => 'qwen/qwen3.8-max',
        'ai.openai.base_url' => 'https://gateway.test/v1',
    ]);
    Sleep::fake();
}

/** @param array<string, mixed> $override */
function oaiBody(array $override = []): array
{
    return array_replace_recursive([
        'id' => 'chatcmpl-1', 'model' => 'qwen3.8-max',
        'choices' => [['index' => 0, 'finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'reasoning_content' => 'thinking', 'content' => '{"ok":true}']]],
        'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 80, 'total_tokens' => 180, 'completion_tokens_details' => ['reasoning_tokens' => 72]],
    ], $override);
}

function oaiGenerate(array $parts = [['text' => 'Set ok.']]): GeminiResult
{
    return app(GeminiClient::class)->generate('preflight', 'SYSTEM-PROMPT', $parts, ['type' => 'object'], 256, 20);
}

beforeEach(fn () => oaiConfigure());

it('posts an OpenAI-compatible chat completion with a Bearer key and json_schema', function () {
    Http::fake(['gateway.test/*' => Http::response(oaiBody())]);

    $result = oaiGenerate([['text' => 'Read the label.'], ['inlineData' => ['mimeType' => 'image/jpeg', 'data' => 'QUJD']]]);

    Http::assertSent(function (Request $r) {
        $b = $r->data();

        return $r->url() === 'https://gateway.test/v1/chat/completions'
            && $r->hasHeader('Authorization', 'Bearer '.OAI_KEY)
            && ! $r->hasHeader('x-goog-api-key')
            && $b['model'] === 'qwen/qwen3.8-max'
            && $b['max_tokens'] === 256
            && $b['messages'][0] === ['role' => 'system', 'content' => 'SYSTEM-PROMPT']
            && $b['messages'][1]['content'][0] === ['type' => 'text', 'text' => 'Read the label.']
            && $b['messages'][1]['content'][1]['image_url']['url'] === 'data:image/jpeg;base64,QUJD'
            && $b['response_format']['type'] === 'json_schema'
            && $b['response_format']['json_schema']['schema'] === ['type' => 'object']
            && ! array_key_exists('store', $b)
            && ! str_contains($r->url(), OAI_KEY);
    });
    expect($result->json)->toBe(['ok' => true])
        ->and($result->modelVersion)->toBe('qwen3.8-max')
        ->and($result->usage)->toBe(['prompt' => 100, 'candidates' => 8, 'thoughts' => 72, 'total' => 180]);
});

it('ignores reasoning text and never logs prompts, keys or output', function () {
    Http::fake(['gateway.test/*' => Http::response(oaiBody())]);
    $logged = [];
    Log::listen(function ($m) use (&$logged) {
        $logged[] = $m->message.json_encode($m->context);
    });

    oaiGenerate([['text' => 'SECRET-PROMPT-TEXT']]);

    expect(implode(' ', $logged))->not->toContain(OAI_KEY)->not->toContain('SECRET-PROMPT-TEXT')->not->toContain('thinking');
});

it('refuses without a key and makes no HTTP call', function () {
    config(['ai.openai.api_key' => '']);
    Http::fake();

    expect(fn () => oaiGenerate())->toThrow(AiException::class);
    Http::assertNothingSent();
});

it('rejects a malformed model id', function () {
    config(['ai.openai.model' => 'qwen/../evil model']);
    Http::fake();

    try {
        oaiGenerate();
    } catch (AiException $e) {
        expect($e->errorCode)->toBe('POLICY_CONFIG_INVALID');
    }
    Http::assertNothingSent();
});

it('maps unusable completions to AI_OUTPUT_INVALID', function (array $override) {
    Http::fake(['gateway.test/*' => Http::response(oaiBody($override))]);

    try {
        oaiGenerate();
        $this->fail('expected AiException');
    } catch (AiException $e) {
        expect($e->errorCode)->toBe('AI_OUTPUT_INVALID');
    }
    Http::assertSentCount(1);
})->with([
    'truncated (length)' => [['choices' => [['finish_reason' => 'length']]]],
    'content filter' => [['choices' => [['finish_reason' => 'content_filter']]]],
    'empty content' => [['choices' => [['message' => ['content' => '  ']]]]],
    'not json' => [['choices' => [['message' => ['content' => 'sure: ok']]]]],
    'json scalar' => [['choices' => [['message' => ['content' => '5']]]]],
    'tool call' => [['choices' => [['message' => ['tool_calls' => [['id' => 'x']]]]]]],
]);

it('retries once on a short Retry-After and succeeds', function () {
    Http::fake(['gateway.test/*' => Http::sequence()->push(['error' => ['message' => 'busy']], 429, ['Retry-After' => '2'])->push(oaiBody())]);

    expect(oaiGenerate()->json)->toBe(['ok' => true]);
    Http::assertSentCount(2);
});

it('does not retry when Retry-After exceeds the deadline', function () {
    Http::fake(['gateway.test/*' => Http::response(['error' => ['message' => 'Free Qwen is at capacity']], 429, ['Retry-After' => '26'])]);

    try {
        oaiGenerate();
        $this->fail('expected AiException');
    } catch (AiException $e) {
        expect($e->errorCode)->toBe('AI_PROVIDER_ERROR')->and($e->getMessage())->not->toContain('capacity');
    }
    Http::assertSentCount(1);
});

it('does not retry other 4xx errors', function () {
    Http::fake(['gateway.test/*' => Http::response(['error' => ['message' => 'bad key']], 401)]);

    expect(fn () => oaiGenerate())->toThrow(AiException::class);
    Http::assertSentCount(1);
});
