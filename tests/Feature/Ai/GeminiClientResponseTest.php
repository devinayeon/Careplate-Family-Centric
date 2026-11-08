<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

require_once __DIR__.'/GeminiTestSupport.php';

beforeEach(fn () => geminiConfigure());

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
