<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/ScanTestSupport.php';

beforeEach(fn () => scanSetUp());

test('quota exhaustion returns 429 before any provider call', function () {
    config(['ai.quotas.scan.per_minute' => 1]);
    $user = scanUser();
    Http::fake();

    // A rejected upload still spends quota but never reaches the provider.
    scanPost($user, upload('plain text', 'label.jpg'))->assertStatus(415);
    $response = scanPost($user, scanJpeg());

    expectScanError($response, 429, 'RATE_LIMITED', true);
    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0);
    Http::assertNothingSent();
});

test('the daily quota is enforced too', function () {
    config(['ai.quotas.scan.per_minute' => 100, 'ai.quotas.scan.per_day' => 2]);
    $user = scanUser();
    fakeProvider(rawLabel());

    scanPost($user, scanJpeg())->assertOk();
    scanPost($user, scanJpeg())->assertOk();
    expectScanError(scanPost($user, scanJpeg()), 429, 'RATE_LIMITED', true);
    Http::assertSentCount(2);
});

test('scan content never reaches the database, logs, cache or leftover temp files', function () {
    $marker = 'CANARY-SCAN-MARKER-5d2e9a';
    $user = scanUser();
    $logFile = storage_path('logs/laravel.log');
    $logBefore = is_file($logFile) ? filesize($logFile) : 0;
    $tmpBefore = glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'*') ?: [];
    [$logged] = captureLogs();

    fakeProvider(rawLabel(
        ['sugars_total' => rawNutrient(12, 'g', 'exact', "sugars {$marker} 12 g")],
        ['serving_size' => ['value' => 30, 'unit' => 'g', 'household_text' => "{$marker} keping"]],
    ));
    $image = scanJpeg();
    $response = scanPost($user, $image)->assertOk();

    // The marker is part of the public answer, so the only sinks that matter are stores.
    expect($response->getContent())->toContain($marker);

    $sent = null;
    Http::assertSent(function ($request) use (&$sent) {
        $sent = $request->data()['contents'][0]['parts'];

        return true;
    });
    $base64 = collect($sent)->first(fn ($p) => isset($p['inlineData']))['inlineData']['data'];
    $needles = [$marker, substr($base64, 200, 60), 'inlineData'];

    $tables = collect(Schema::getTables())->pluck('name');
    foreach ($tables as $table) {
        $dump = json_encode(DB::table($table)->get());
        foreach ($needles as $needle) {
            expect($dump)->not->toContain($needle, "table {$table}");
        }
    }

    $store = Cache::getStore();
    $cacheDump = serialize((fn () => $this->storage)->call($store));
    $logs = implode("\n", $logged->getArrayCopy());
    $fileLogs = is_file($logFile) ? substr((string) file_get_contents($logFile), $logBefore) : '';
    foreach ($needles as $needle) {
        expect($cacheDump)->not->toContain($needle)
            ->and($logs)->not->toContain($needle)
            ->and($fileLogs)->not->toContain($needle);
    }

    foreach (['framework/sessions', 'framework/cache', 'app'] as $dir) {
        $files = glob(storage_path($dir).'/*') ?: [];
        foreach (array_filter($files, 'is_file') as $file) {
            if (basename($file) !== '.gitignore') {
                expect(file_get_contents($file))->not->toContain($marker);
            }
        }
    }

    expect(glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'*') ?: [])->toEqualCanonicalizing($tmpBefore)
        ->and(file_exists($image->getPathname()))->toBeFalse()
        ->and(session()->all())->not->toHaveKey('_old_input');
});
