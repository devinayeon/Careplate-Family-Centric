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

    $this->artisan('ai:eval', ['suite' => 'chat'])->assertExitCode(2);
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
