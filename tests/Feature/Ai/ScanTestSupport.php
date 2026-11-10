<?php

use App\Models\User;
use Database\Seeders\AiPermissionSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

require_once __DIR__.'/GeminiTestSupport.php';
require_once __DIR__.'/ImageFixtures.php';
require_once __DIR__.'/../../Unit/Ai/RawLabelFactory.php';

/** Shared helpers for the scan endpoint feature tests (synthetic data only). */
function scanSetUp(): void
{
    geminiConfigure();
    test()->seed(AiPermissionSeeder::class);
}

function scanUser(string $role = 'nutritionist'): User
{
    $user = User::factory()->create();
    if ($role !== '') {
        $user->assignRole($role);
    }

    return $user;
}

function scanJpeg(string $name = 'label.jpg'): UploadedFile
{
    return upload(gdEncode(solidImage(80, 60)), $name);
}

/** Replace the HTTP factory so stubs from an earlier call in the same test cannot win. */
function httpFake(mixed $response): void
{
    Http::swap(new Factory);
    Http::fake([GEMINI_URL => $response]);
}

/** Fake a successful provider reply carrying the given raw OCR payload. */
function fakeProvider(array $raw): void
{
    httpFake(Http::response(geminiOk($raw)));
}

function scanPost(?User $user, mixed $image = null, array $extra = []): TestResponse
{
    $test = test();
    if ($user !== null) {
        $test = $test->actingAs($user);
    }
    $data = $image === null ? $extra : ['image' => $image] + $extra;

    return $test->post('/ai/v1/scan', $data, ['Accept' => 'application/json']);
}

/** Assert the envelope status/code/retryable of an error response. */
function expectScanError(TestResponse $response, int $status, string $code, bool $retryable = false): void
{
    $response->assertStatus($status)
        ->assertJsonPath('error.code', $code)
        ->assertJsonPath('error.retryable', $retryable);
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and(array_keys($response->json()))->toEqualCanonicalizing(['error', 'request_id']);
}
