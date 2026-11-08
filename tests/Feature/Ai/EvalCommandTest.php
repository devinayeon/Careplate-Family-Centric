<?php

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/GeminiTestSupport.php';
require_once __DIR__.'/../../Unit/Ai/RawLabelFactory.php';

function evalOutDir(): string
{
    $dir = sys_get_temp_dir().'/ai-eval-test-'.bin2hex(random_bytes(4));
    mkdir($dir);

    return $dir;
}

it('refuses to run without enabled AI and a key, sending nothing', function () {
    config(['ai.enabled' => false, 'ai.gemini.api_key' => '']);
    Http::fake();
    $dir = evalOutDir();

    $this->artisan('ai:eval', ['suite' => 'scan', '--out-dir' => $dir])->assertFailed();

    Http::assertNothingSent();
    expect(glob($dir.'/*'))->toBe([]);
    File::deleteDirectory($dir);
});

it('rejects unknown suites and splits before any request', function () {
    geminiConfigure();
    Http::fake();

    $this->artisan('ai:eval', ['suite' => 'bogus'])->assertExitCode(2);
    $this->artisan('ai:eval', ['suite' => 'scan', '--split' => 'everything'])->assertExitCode(2);

    Http::assertNothingSent();
});

it('sends one request per dev image and writes a sanitized result file', function () {
    geminiConfigure();
    Http::swap(new Factory);
    Http::fake([GEMINI_URL => Http::response(geminiOk(rawLabel()))]);
    $dir = evalOutDir();

    $this->artisan('ai:eval', ['suite' => 'scan', '--split' => 'dev', '--out-dir' => $dir])->assertSuccessful();

    Http::assertSentCount(3);
    $files = glob($dir.'/scan-*.json');
    expect($files)->toHaveCount(1);
    $raw = file_get_contents($files[0]);
    $json = json_decode($raw, true);

    expect($json['requests'])->toBe(3)
        ->and($json['split'])->toBe('dev')
        ->and($json['truth_status'])->toBe('unverified_by_human')
        ->and(array_column($json['items'], 'id'))->toBe(['SMP-01', 'SMP-02', 'SMP-03'])
        ->and($json['items'][0]['usage'])->toBe(['prompt' => 11, 'candidates' => 7, 'thoughts' => 3, 'total' => 21])
        ->and($json['items'][2]['expected_outcome'])->toBe('unreadable')
        ->and($json['items'][2]['unsafe_count'])->toBeGreaterThan(0)
        ->and($json['summary']['images'])->toBe(3)
        ->and($raw)->not->toContain('base64')->not->toContain('inlineData')->not->toContain(GEMINI_FAKE_KEY);
    File::deleteDirectory($dir);
});

it('records provider failures per image instead of aborting the run', function () {
    geminiConfigure();
    Http::swap(new Factory);
    Http::fake([GEMINI_URL => Http::response(geminiOk(['wrong' => 'shape']))]);
    $dir = evalOutDir();

    $this->artisan('ai:eval', ['suite' => 'scan', '--split' => 'dev', '--out-dir' => $dir])->assertSuccessful();

    $json = json_decode(file_get_contents(glob($dir.'/scan-*.json')[0]), true);
    expect($json['summary']['invalid_output'])->toBe(3)
        ->and($json['items'][0]['outcome'])->toBe('error');
    File::deleteDirectory($dir);
});

it('repeats every image --runs times and aggregates per field and per kind', function () {
    geminiConfigure();
    Http::swap(new Factory);
    Http::fake([GEMINI_URL => Http::response(geminiOk(rawLabel()))]);
    $dir = evalOutDir();

    $this->artisan('ai:eval', ['suite' => 'scan', '--split' => 'dev', '--runs' => 2, '--out-dir' => $dir])->assertSuccessful();

    Http::assertSentCount(6);
    $json = json_decode(file_get_contents(glob($dir.'/scan-*.json')[0]), true);
    expect($json['requests'])->toBe(6)
        ->and($json['runs'])->toBe(2)
        ->and($json['summary']['images'])->toBe(6)
        ->and(array_column($json['items'], 'run'))->toBe([1, 1, 1, 2, 2, 2])
        ->and(array_keys($json['summary']['by_kind']))->toBe(['web_sample'])
        ->and($json['summary']['per_image']['SMP-01']['runs'])->toBe(2)
        ->and($json['manifest'])->toBe('ai/evals/ocr/manifest.csv');
    File::deleteDirectory($dir);
});

it('rejects --runs outside 1..5 before any request', function (string $runs) {
    geminiConfigure();
    Http::fake();

    $this->artisan('ai:eval', ['suite' => 'scan', '--runs' => $runs])->assertExitCode(2);

    Http::assertNothingSent();
})->with(['0', '6', '-1', 'abc', '2.5']);

it('only reads manifests under ai/evals so no other image can be sent', function (string $path) {
    geminiConfigure();
    Http::fake();

    $this->artisan('ai:eval', ['suite' => 'scan', '--manifest' => $path])->assertExitCode(2);

    Http::assertNothingSent();
})->with(['composer.json', '../outside.csv', 'ai/evals/../../composer.json', 'ai/evals/nope.csv']);

it('runs the synthetic Indonesian manifest and records its kind and truth status', function () {
    geminiConfigure();
    Http::swap(new Factory);
    Http::fake([GEMINI_URL => Http::response(geminiOk(rawLabel()))]);
    $dir = evalOutDir();

    $this->artisan('ai:eval', ['suite' => 'scan', '--manifest' => 'ai/evals/ocr-id/synthetic/manifest.csv', '--runs' => 2, '--out-dir' => $dir])->assertSuccessful();

    Http::assertSentCount(12);
    $raw = file_get_contents(glob($dir.'/scan-*.json')[0]);
    $json = json_decode($raw, true);
    expect($json['kind'])->toBe('synthetic_id')
        ->and($json['truth_status'])->toBe('exact_by_construction')
        ->and($json['manifest'])->toBe('ai/evals/ocr-id/synthetic/manifest.csv')
        ->and($json['items'][0]['kind'])->toBe('synthetic_id')
        ->and($raw)->not->toContain('base64')->not->toContain(GEMINI_FAKE_KEY);
    File::deleteDirectory($dir);
});
