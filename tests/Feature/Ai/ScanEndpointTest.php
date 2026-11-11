<?php

use App\Services\Ai\Scan\LabelNormalizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/ScanTestSupport.php';

beforeEach(fn () => scanSetUp());

test('a readable label returns the contract shaped response', function () {
    fakeProvider(rawLabel());

    $response = scanPost(scanUser(), scanJpeg());

    $response->assertOk();
    $json = $response->json();
    expect(array_keys($json))->toBe(['schema_version', 'request_id', 'extraction_status', 'extraction', 'normalized_per_serving', 'assessment', 'warnings'])
        ->and($json['schema_version'])->toBe('1.0')
        ->and($json['request_id'])->toBe($response->headers->get('X-Request-Id'))
        ->and($json['request_id'])->toMatch('/^req_[A-Za-z0-9]+$/')
        ->and($json['extraction_status'])->toBe('complete')
        ->and(array_keys($json['extraction']))->toBe(['label_basis', 'serving_size', 'servings_per_package', 'nutrients', 'needs_review'])
        ->and(array_keys($json['extraction']['nutrients']))->toBe(['energy_kcal', 'fat_g', 'sugars_total_g', 'protein_g', 'carbohydrate_g', 'sodium_mg'])
        ->and(array_keys($json['extraction']['nutrients']['sodium_mg']))->toBe(['value', 'qualifier', 'unit', 'evidence', 'label_percent_akg'])
        ->and($json['extraction']['nutrients']['sodium_mg']['value'])->toBe(450)
        ->and($json['normalized_per_serving'])->toBe(['energy_kcal' => 150, 'fat_g' => 8, 'sugars_total_g' => 12, 'protein_g' => 3, 'carbohydrate_g' => 20, 'sodium_mg' => 450])
        ->and(array_keys($json['assessment']))->toBe(['policy', 'daily_reference_contribution_percent', 'flags', 'verdict', 'summary'])
        ->and($json['assessment']['policy']['status'])->toBe('provisional')
        ->and($json['assessment']['daily_reference_contribution_percent'])->toBe(['sugars_total' => 24, 'sodium' => 22.5, 'fat_total' => 11.94])
        ->and($json['assessment']['flags'])->toBe([])
        ->and($json['assessment']['verdict'])->toBe('policy_pending_validation')
        ->and($json['assessment']['summary'])->toBe('Kontribusi gula 24%, natrium 22,5%, dan lemak 11,94% dari pembanding edukasi per sajian. Klasifikasi menunggu validasi kebijakan.')
        ->and($json['warnings'])->toBeArray()->not->toBeEmpty()
        ->and($response->getContent())->not->toContain('"values"')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('the provider call carries the prepared jpeg, the ocr prompt, the provider schema and the caps', function () {
    fakeProvider(rawLabel());

    scanPost(scanUser(), upload(gdEncode(solidImage(60, 40), 'png'), 'label.png'))->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) {
        $body = $request->data();
        $parts = $body['contents'][0]['parts'];
        $image = collect($parts)->first(fn ($p) => isset($p['inlineData']));
        $bytes = base64_decode($image['inlineData']['data'], true);

        return $request->url() === GEMINI_URL
            && $body['systemInstruction']['parts'][0]['text'] === file_get_contents(base_path('ai/prompts/ocr-system.txt'))
            && $body['generationConfig']['responseFormat']['text']['schema'] === json_decode(file_get_contents(base_path('ai/schemas/provider-ocr.json')), true)
            && $body['generationConfig']['maxOutputTokens'] === config('ai.scan.max_output_tokens')
            && $body['store'] === false
            && isset($parts[0]['text']) && is_string($parts[0]['text'])
            && $image['inlineData']['mimeType'] === 'image/jpeg'
            && is_string($bytes) && str_starts_with($bytes, "\xFF\xD8\xFF")
            && ! $request->hasHeader('Cookie');
    });
});

test('a partial label stays usable and is reported as partial', function () {
    fakeProvider(rawLabel(['sugars_total' => rawUnknown(), 'sodium' => rawUnknown()]));

    $json = scanPost(scanUser(), scanJpeg())->assertOk()->json();

    expect($json['extraction_status'])->toBe('partial')
        ->and($json['normalized_per_serving']['sugars_total_g'])->toBeNull()
        ->and($json['normalized_per_serving']['sodium_mg'])->toBeNull()
        ->and($json['normalized_per_serving']['fat_g'])->toBe(8)
        ->and($json['extraction']['nutrients']['sugars_total_g']['value'])->toBeNull()
        ->and($json['assessment']['daily_reference_contribution_percent'])->toBe(['sugars_total' => null, 'sodium' => null, 'fat_total' => 11.94]);
});

test('an unreadable label returns 422 OCR_UNREADABLE', function () {
    $user = scanUser();
    config(['ai.quotas.scan.per_minute' => 100]);

    fakeProvider(rawLabel([], ['readable' => false]));
    expectScanError(scanPost($user, scanJpeg()), 422, 'OCR_UNREADABLE');

    $allUnknown = array_fill_keys(['energy', 'fat', 'sugars_total', 'protein', 'carbohydrate', 'sodium'], rawUnknown());
    fakeProvider(rawLabel($allUnknown));
    expectScanError(scanPost($user, scanJpeg()), 422, 'OCR_UNREADABLE');
});

test('non conforming provider output is AI_OUTPUT_INVALID', function () {
    $user = scanUser();
    config(['ai.quotas.scan.per_minute' => 100]);

    foreach ([['foo' => 'bar'], [1, 2, 3], rawLabel([], ['extra' => true])] as $payload) {
        fakeProvider($payload);
        expectScanError(scanPost($user, scanJpeg()), 502, 'AI_OUTPUT_INVALID', true);
    }
});

test('provider 503 twice maps to 502 and a transport timeout to 504', function () {
    $user = scanUser();
    config(['ai.quotas.scan.per_minute' => 100]);

    httpFake(Http::response(['error' => ['message' => 'SECRET-PROVIDER-TEXT']], 503));
    $response = scanPost($user, scanJpeg());
    expectScanError($response, 502, 'AI_PROVIDER_ERROR', true);
    Http::assertSentCount(2);
    expect($response->getContent())->not->toContain('SECRET-PROVIDER-TEXT');

    httpFake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));
    expectScanError(scanPost($user, scanJpeg()), 504, 'AI_TIMEOUT', true);
});

test('a disabled ai feature returns 503 AI_DISABLED without a provider call', function () {
    config(['ai.enabled' => false]);
    Http::fake();

    expectScanError(scanPost(scanUser(), scanJpeg()), 503, 'AI_DISABLED');
    Http::assertNothingSent();
});

test('unauthenticated and unauthorised users never reach the provider', function () {
    Http::fake();

    expectScanError(scanPost(null, scanJpeg()), 401, 'UNAUTHENTICATED');
    expectScanError(scanPost(scanUser(''), scanJpeg()), 403, 'FORBIDDEN');
    Http::assertNothingSent();
});

test('a request without a csrf token is rejected before the provider', function () {
    // Laravel skips the CSRF check while the app environment is "testing".
    app()->instance('env', 'local');
    Http::fake();

    expectScanError(scanPost(scanUser(), scanJpeg()), 419, 'CSRF_MISMATCH');
    Http::assertNothingSent();
});

test('input validation errors never reach the provider', function () {
    Http::fake();
    $user = scanUser();
    config(['ai.quotas.scan.per_minute' => 100]);

    expectScanError(scanPost($user, null), 422, 'INVALID_INPUT');
    expectScanError(scanPost($user, 'not-a-file'), 422, 'INVALID_INPUT');
    expectScanError(scanPost($user, [scanJpeg(), scanJpeg()]), 422, 'INVALID_INPUT');
    expectScanError(scanPost($user, upload("\xFF\xD8\xFF\xE0garbage-not-an-image")), 422, 'INVALID_INPUT');
    expectScanError(scanPost($user, upload('plain text pretending', 'label.jpg')), 415, 'UNSUPPORTED_MEDIA_TYPE');
    expectScanError(scanPost($user, upload(gdEncode(solidImage(10, 10), 'gif'), 'label.png')), 415, 'UNSUPPORTED_MEDIA_TYPE');
    expectScanError(scanPost($user, upload('<svg xmlns="http://www.w3.org/2000/svg"/>', 'label.svg')), 415, 'UNSUPPORTED_MEDIA_TYPE');
    Http::assertNothingSent();
});

test('an oversized upload returns 413 before the provider', function () {
    Http::fake();
    config(['ai.scan.max_bytes' => 500]);

    expectScanError(scanPost(scanUser(), upload(gdEncode(quadrantImage(200, 200)))), 413, 'IMAGE_TOO_LARGE');
    Http::assertNothingSent();
});

test('the temporary upload is removed after success and after failure', function () {
    $user = scanUser();
    config(['ai.quotas.scan.per_minute' => 100]);

    fakeProvider(rawLabel());
    $ok = scanJpeg();
    scanPost($user, $ok)->assertOk();
    expect(file_exists($ok->getPathname()))->toBeFalse();

    $bad = upload('plain text', 'label.jpg');
    scanPost($user, $bad)->assertStatus(415);
    expect(file_exists($bad->getPathname()))->toBeFalse();

    httpFake(Http::response([], 503));
    $failed = scanJpeg();
    scanPost($user, $failed)->assertStatus(502);
    expect(file_exists($failed->getPathname()))->toBeFalse();
});

test('an unexpected failure inside the pipeline is a bare 500 INTERNAL without content', function () {
    fakeProvider(rawLabel());
    $this->mock(LabelNormalizer::class)->shouldReceive('normalize')->andThrow(new RuntimeException('LEAK-CANARY-4411'));
    [$logged] = captureLogs();

    $response = scanPost(scanUser(), scanJpeg());

    expectScanError($response, 500, 'INTERNAL');
    expect($response->getContent())->not->toContain('LEAK-CANARY-4411')
        ->and(implode("\n", $logged->getArrayCopy()))->not->toContain('LEAK-CANARY-4411');
});

// Owner decision 2026-11-11: free tier = 10 scans per user per day, a hard-coded number in config/ai.php.
test('free tier: a user scans 10 labels per day and the 11th is RATE_LIMITED before any provider call', function () {
    $user = scanUser();
    config(['ai.quotas.scan.per_minute' => 100]);
    expect(config('ai.quotas.scan.per_day'))->toBe(10);

    fakeProvider(rawLabel());
    foreach (range(1, 10) as $i) {
        scanPost($user, scanJpeg())->assertOk();
    }
    Http::assertSentCount(10);

    expectScanError(scanPost($user, scanJpeg()), 429, 'RATE_LIMITED', true);
    Http::assertSentCount(10);

    // The quota is per user: another user still scans.
    scanPost(scanUser(), scanJpeg())->assertOk();
});
