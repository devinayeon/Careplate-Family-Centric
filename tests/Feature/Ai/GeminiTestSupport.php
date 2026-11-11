<?php

use App\Exceptions\Ai\AiException;
use App\Services\Ai\Gemini\GeminiClient;
use App\Services\Ai\Gemini\GeminiResult;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Sleep;

const GEMINI_FAKE_KEY = 'test-key-not-real';
const GEMINI_URL = 'https://gemini.test/v1beta/models/gemini-test:generateContent';
const PROMPT_CANARY = 'CANARY-PROMPT-TEXT-91f3';
const IMAGE_CANARY = 'Q0FOQVJZLUJBU0U2NC1JTUFHRQ==';
const OUTPUT_CANARY = 'CANARY-OUTPUT-VALUE-77c1';

function geminiConfigure(): void
{
    config([
        'ai.enabled' => true,
        'ai.gemini.api_key' => GEMINI_FAKE_KEY,
        'ai.gemini.model' => 'gemini-test',
        'ai.gemini.base_url' => 'https://gemini.test/v1beta',
        'ai.http.connect_timeout_seconds' => 5,
        'ai.http.max_attempts' => 2,
    ]);
    Sleep::fake();
}

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
