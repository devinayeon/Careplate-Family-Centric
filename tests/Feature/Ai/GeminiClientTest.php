<?php

use App\Exceptions\Ai\AiException;
use App\Http\Middleware\Ai\AiResponseHeaders;
use App\Services\Ai\Gemini\GeminiClient;
use App\Services\Ai\Gemini\GeminiResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

const GEMINI_FAKE_KEY = 'test-key-not-real';
const GEMINI_URL = 'https://gemini.test/v1beta/models/gemini-test:generateContent';
const PROMPT_CANARY = 'CANARY-PROMPT-TEXT-91f3';
const IMAGE_CANARY = 'Q0FOQVJZLUJBU0U2NC1JTUFHRQ==';
const OUTPUT_CANARY = 'CANARY-OUTPUT-VALUE-77c1';

beforeEach(function () {
    config([
        'ai.enabled' => true,
        'ai.gemini.api_key' => GEMINI_FAKE_KEY,
        'ai.gemini.model' => 'gemini-test',
        'ai.gemini.base_url' => 'https://gemini.test/v1beta',
        'ai.http.connect_timeout_seconds' => 5,
        'ai.http.max_attempts' => 2,
    ]);
    Sleep::fake();
});

function geminiOk(array $payload = ['ok' => true], array $overrides = []): array
{
    return array_replace_recursive([
        'candidates' => [[
            'content' => ['role' => 'model', 'parts' => [['text' => json_encode($payload)]]],
            'finishReason' => 'STOP',
        ]],
        'usageMetadata' => [
            'promptTokenCount' => 11,
            'candidatesTokenCount' => 7,
            'thoughtsTokenCount' => 3,
            'totalTokenCount' => 21,
        ],
        'modelVersion' => 'gemini-test-001',
    ], $overrides);
}

function geminiText(string $text, string $finish = 'STOP'): array
{
    return ['candidates' => [['finishReason' => $finish, 'content' => ['parts' => [['text' => $text]]]]]];
}

function geminiGenerate(int $deadline = 20): GeminiResult
{
    return app(GeminiClient::class)->generate(
        'scan',
        'system '.PROMPT_CANARY,
        [['text' => 'user '.PROMPT_CANARY], ['inlineData' => ['mimeType' => 'image/jpeg', 'data' => IMAGE_CANARY]]],
        ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok']],
        512,
        $deadline,
    );
}

function geminiError(int $deadline = 20): AiException
{
    try {
        geminiGenerate($deadline);
    } catch (AiException $e) {
        return $e;
    }
    throw new RuntimeException('expected AiException');
}

/** @return array{0: ArrayObject<int, string>} */
function captureLogs(): array
{
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, function (MessageLogged $e) use ($logged) {
        $logged[] = $e->level.' '.$e->message.' '.json_encode($e->context);
    });

    return [$logged];
}

it('returns decoded json, model version and usage on STOP', function () {
    Http::fake([GEMINI_URL => Http::response(geminiOk(['ok' => true]))]);

    $result = geminiGenerate();

    expect($result->json)->toBe(['ok' => true])
        ->and($result->modelVersion)->toBe('gemini-test-001')
        ->and($result->usage)->toBe(['prompt' => 11, 'candidates' => 7, 'thoughts' => 3, 'total' => 21])
        ->and($result->latencyMs)->toBeInt()->toBeGreaterThanOrEqual(0)
        ->and($result->finishReason)->toBe('STOP');
});

it('sets usage fields to null when the provider omits them', function () {
    Http::fake([GEMINI_URL => Http::response(geminiText('{"ok":true}'))]);

    $result = geminiGenerate();

    expect($result->usage)->toBe(['prompt' => null, 'candidates' => null, 'thoughts' => null, 'total' => null])
        ->and($result->modelVersion)->toBeNull();
});

it('sends the documented request shape and no banned fields', function () {
    Http::fake([GEMINI_URL => Http::response(geminiOk())]);

    geminiGenerate();

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) {
        $body = $request->data();
        $config = $body['generationConfig'];

        expect($request->url())->toBe(GEMINI_URL)
            ->and($request->method())->toBe('POST')
            ->and($request->header('x-goog-api-key'))->toBe([GEMINI_FAKE_KEY])
            ->and($body['store'])->toBeFalse()
            ->and($body['systemInstruction'])->toBe(['parts' => [['text' => 'system '.PROMPT_CANARY]]])
            ->and($body['contents'])->toHaveCount(1)
            ->and($body['contents'][0]['role'])->toBe('user')
            ->and($body['contents'][0]['parts'][1]['inlineData']['data'])->toBe(IMAGE_CANARY)
            ->and($config['thinkingConfig'])->toBe(['thinkingLevel' => 'low'])
            ->and($config['maxOutputTokens'])->toBe(512)
            ->and($config['responseFormat']['text']['mimeType'])->toBe('APPLICATION_JSON')
            ->and($config['responseFormat']['text']['schema']['properties']['ok']['type'])->toBe('boolean');

        foreach (['temperature', 'topP', 'topK', 'candidateCount'] as $banned) {
            expect($config)->not->toHaveKey($banned)->and($body)->not->toHaveKey($banned);
        }
        expect($request->url())->not->toContain('key=');

        return true;
    });
});

it('throws AI_DISABLED without any HTTP call when disabled or keyless', function (array $config) {
    Http::fake();
    config($config);

    $error = geminiError();

    expect($error->errorCode)->toBe('AI_DISABLED')->and($error->status)->toBe(503);
    Http::assertNothingSent();
})->with([
    'flag off' => [['ai.enabled' => false]],
    'empty key' => [['ai.gemini.api_key' => '']],
    'null key' => [['ai.gemini.api_key' => null]],
]);

it('refuses a model id that could alter the endpoint path', function () {
    Http::fake();
    config(['ai.gemini.model' => '../evil:generate']);

    expect(geminiError()->errorCode)->toBe('POLICY_CONFIG_INVALID');
    Http::assertNothingSent();
});

it('maps a non-STOP or unusable provider answer to AI_OUTPUT_INVALID', function (array $body) {
    Http::fake([GEMINI_URL => Http::response($body)]);

    $error = geminiError();

    expect($error->errorCode)->toBe('AI_OUTPUT_INVALID')->and($error->status)->toBe(502)->and($error->retryable)->toBeTrue();
    Http::assertSentCount(1);
})->with([
    'MAX_TOKENS' => [geminiText('{"ok":tr', 'MAX_TOKENS')],
    'SAFETY finish' => [geminiText('', 'SAFETY')],
    'finish missing' => [['candidates' => [['content' => ['parts' => [['text' => '{"ok":true}']]]]]]],
    'prompt blocked' => [['promptFeedback' => ['blockReason' => 'SAFETY']]],
    'blocked but candidate' => [geminiText('{"ok":true}') + ['promptFeedback' => ['blockReason' => 'OTHER']]],
    'no candidates' => [['candidates' => []]],
    'empty object' => [[]],
    'empty text' => [geminiText('')],
    'no parts' => [['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => []]]]]],
    'invalid json' => [geminiText('{"ok": tru')],
    'json scalar' => [geminiText('"hello"')],
    'tool call' => [['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['functionCall' => ['name' => 'x', 'args' => []]]]]]]]],
]);

it('maps an empty or non-JSON HTTP body to AI_PROVIDER_ERROR or AI_OUTPUT_INVALID, never a crash', function () {
    Http::fake([GEMINI_URL => Http::response('<html>oops</html>', 200)]);

    expect(geminiError()->errorCode)->toBe('AI_OUTPUT_INVALID');
});

it('ignores thought parts when reading the final JSON', function () {
    Http::fake([GEMINI_URL => Http::response(['candidates' => [[
        'finishReason' => 'STOP',
        'content' => ['parts' => [['text' => 'thinking...', 'thought' => true], ['text' => '{"ok":false}']]],
    ]]])]);

    expect(geminiGenerate()->json)->toBe(['ok' => false]);
});

it('does not repair or regenerate invalid JSON', function () {
    Http::fake([GEMINI_URL => Http::response(geminiText('Sure! {"ok":true}'))]);

    expect(geminiError()->errorCode)->toBe('AI_OUTPUT_INVALID');
    Http::assertSentCount(1);
});

it('retries once on 503 then succeeds', function () {
    Http::fake([GEMINI_URL => Http::sequence()->push(['error' => 'x'], 503)->push(geminiOk())]);

    expect(geminiGenerate()->json)->toBe(['ok' => true]);
    Http::assertSentCount(2);
});

it('retries once on a connection error then succeeds', function () {
    Http::fake([GEMINI_URL => Http::sequence()->pushFailedConnection('cURL error 7: Failed to connect')->push(geminiOk())]);

    expect(geminiGenerate()->json)->toBe(['ok' => true]);
    Http::assertSentCount(2);
});

it('never makes more than two attempts', function () {
    Http::fake([GEMINI_URL => Http::response(['error' => 'x'], 500)]);

    $error = geminiError();

    expect($error->errorCode)->toBe('AI_PROVIDER_ERROR')->and($error->status)->toBe(502)->and($error->retryable)->toBeTrue();
    Http::assertSentCount(2);
});

it('does not retry on other 4xx and never leaks the provider message', function (int $status) {
    Http::fake([GEMINI_URL => Http::response(['error' => ['message' => PROMPT_CANARY]], $status)]);

    $error = geminiError();

    expect($error->errorCode)->toBe('AI_PROVIDER_ERROR')->and($error->getMessage())->not->toContain(PROMPT_CANARY);
    Http::assertSentCount(1);
})->with([400, 401, 403, 404]);

it('retries a 429 when Retry-After fits and honours it', function () {
    Http::fake([GEMINI_URL => Http::sequence()->push([], 429, ['Retry-After' => '2'])->push(geminiOk())]);

    expect(geminiGenerate(20)->json)->toBe(['ok' => true]);
    Http::assertSentCount(2);
    Sleep::assertSlept(fn ($duration) => $duration->totalMilliseconds >= 2000, 1);
});

it('does not retry a 429 whose Retry-After does not fit the deadline', function () {
    Http::fake([GEMINI_URL => Http::response([], 429, ['Retry-After' => '120'])]);

    $error = geminiError();

    expect($error->errorCode)->toBe('AI_PROVIDER_ERROR');
    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
});

it('maps a transport timeout to AI_TIMEOUT without retrying', function () {
    $calls = 0;
    Http::fake([GEMINI_URL => function () use (&$calls) {
        $calls++;
        throw new ConnectionException('cURL error 28: Operation timed out after 20001 ms with 0 bytes received');
    }]);

    $error = geminiError();

    expect($error->errorCode)->toBe('AI_TIMEOUT')->and($error->status)->toBe(504)->and($error->retryable)->toBeTrue()
        ->and($calls)->toBe(1);
});

it('maps a persistent connection failure to AI_PROVIDER_ERROR and never leaks the raw message', function () {
    $calls = 0;
    Http::fake([GEMINI_URL => function () use (&$calls) {
        $calls++;
        throw new ConnectionException('cURL error 6: Could not resolve host gemini.test '.PROMPT_CANARY);
    }]);

    $error = geminiError();

    expect($error->errorCode)->toBe('AI_PROVIDER_ERROR')
        ->and($error->getMessage())->not->toContain(PROMPT_CANARY)
        ->and($error->getPrevious())->toBeNull()
        ->and($calls)->toBe(2);
});

it('throws AI_TIMEOUT before any call when the deadline is already spent', function () {
    Http::fake();

    expect(geminiError(0)->errorCode)->toBe('AI_TIMEOUT');
    Http::assertNothingSent();
});

it('uses the remaining deadline as request timeout, not a full timeout per attempt', function () {
    $timeouts = [];
    Http::fake(function (Request $request, array $options) use (&$timeouts) {
        $timeouts[] = $options['timeout'];
        usleep(300_000);

        return count($timeouts) === 1 ? Http::response([], 503) : Http::response(geminiOk());
    });

    geminiGenerate(10);

    expect($timeouts)->toHaveCount(2)
        ->and($timeouts[0])->toBeLessThanOrEqual(10)->toBeGreaterThan(9)
        ->and($timeouts[1])->toBeLessThan($timeouts[0] - 0.25);
});

it('logs one allowlisted line per call without prompt, image, output or key', function () {
    [$logged] = captureLogs();
    Http::fake([GEMINI_URL => Http::response(geminiOk(['ok' => true, 'note' => OUTPUT_CANARY]))]);
    request()->attributes->set(AiResponseHeaders::REQUEST_ID, 'req_testlog1');

    geminiGenerate();

    $aiLines = array_values(array_filter($logged->getArrayCopy(), fn ($l) => str_contains($l, 'ai.gemini')));
    expect($aiLines)->toHaveCount(1)
        ->and($aiLines[0])->toContain('req_testlog1')->toContain('"feature":"scan"')->toContain('gemini-test-001')->toContain('latency_ms');
    foreach ($logged as $line) {
        foreach ([PROMPT_CANARY, IMAGE_CANARY, OUTPUT_CANARY, GEMINI_FAKE_KEY] as $secret) {
            expect($line)->not->toContain($secret);
        }
    }
});

it('logs failures with the error code only', function () {
    [$logged] = captureLogs();
    Http::fake([GEMINI_URL => Http::response(['error' => ['message' => PROMPT_CANARY.' '.GEMINI_FAKE_KEY]], 400)]);

    geminiError();

    $aiLines = array_values(array_filter($logged->getArrayCopy(), fn ($l) => str_contains($l, 'ai.gemini')));
    expect($aiLines)->toHaveCount(1)->and($aiLines[0])->toContain('AI_PROVIDER_ERROR')->toContain('"http_status":400');
    foreach ($logged as $line) {
        expect($line)->not->toContain(PROMPT_CANARY)->not->toContain(GEMINI_FAKE_KEY);
    }
});
