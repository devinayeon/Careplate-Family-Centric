<?php

use App\Http\Middleware\Ai\AiResponseHeaders;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/GeminiTestSupport.php';

beforeEach(fn () => geminiConfigure());

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
