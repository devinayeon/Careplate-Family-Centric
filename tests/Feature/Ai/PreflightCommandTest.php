<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'ai.enabled' => true,
        'ai.gemini.api_key' => 'test-key-not-real',
        'ai.gemini.model' => 'gemini-test',
        'ai.gemini.base_url' => 'https://gemini.test/v1beta',
    ]);
});

it('prints sanitized outcome and never the key', function () {
    Http::fake(['gemini.test/*' => Http::response([
        'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => '{"ok":true}']]]]],
        'usageMetadata' => ['promptTokenCount' => 9, 'candidatesTokenCount' => 4, 'thoughtsTokenCount' => 2, 'totalTokenCount' => 15],
        'modelVersion' => 'gemini-test-001',
    ])]);

    $code = Artisan::call('ai:preflight');
    $out = Artisan::output();

    expect($code)->toBe(0)
        ->and($out)->toContain('ok: true')->toContain('model: gemini-test')->toContain('modelVersion: gemini-test-001')
        ->toContain('finishReason: STOP')->toContain('latency_ms:')->toContain('total=15')
        ->not->toContain('test-key-not-real');
    Http::assertSentCount(1);
});

it('fails with a sanitized error code when the provider rejects', function () {
    Http::fake(['gemini.test/*' => Http::response(['error' => ['message' => 'secret detail test-key-not-real']], 404)]);

    $code = Artisan::call('ai:preflight');
    $out = Artisan::output();

    expect($code)->toBe(1)->and($out)->toContain('AI_PROVIDER_ERROR')->toContain('http_status: 404')
        ->not->toContain('secret detail')->not->toContain('test-key-not-real');
});

it('fails without calling the provider when AI is disabled', function () {
    Http::fake();
    config(['ai.enabled' => false]);

    expect(Artisan::call('ai:preflight'))->toBe(1)->and(Artisan::output())->toContain('AI_DISABLED');
    Http::assertNothingSent();
});
