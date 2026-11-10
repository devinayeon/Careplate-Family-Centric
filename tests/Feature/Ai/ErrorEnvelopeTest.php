<?php

use App\Exceptions\Ai\AiException;
use App\Http\Middleware\Ai\AiResponseHeaders;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Exceptions\UnauthorizedException;

beforeEach(function () {
    Route::middleware(['web', 'auth', AiResponseHeaders::class])->prefix('ai/v1')->group(function () {
        Route::get('_probe', fn () => response()->json(['ok' => true]));
        Route::post('_probe', fn () => response()->json(['ok' => true]));
        Route::get('_ai', fn () => throw AiException::for('AI_TIMEOUT'));
        Route::get('_boom', fn () => throw new RuntimeException('SELECT secret FROM users /tmp/trace'));
        Route::get('_forbidden', fn () => throw new AuthorizationException('nope'));
        Route::get('_spatie', fn () => throw UnauthorizedException::forPermissions(['verify-food']));
        Route::get('_missing', fn () => throw new ModelNotFoundException);
        Route::get('_invalid', fn () => Validator::make(['a' => ''], ['a' => 'required'])->validate());
        Route::get('_limited', fn () => response()->json(['ok' => true]))->middleware('throttle:ai-scan');
    });
});

function assertEnvelope($response, int $status, string $code, bool $retryable = false): void
{
    $response->assertStatus($status)
        ->assertJsonPath('error.code', $code)
        ->assertJsonPath('error.retryable', $retryable);
    $json = $response->json();
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($json['request_id'])->toMatch('/^req_[A-Za-z0-9]+$/')
        ->and($response->headers->get('X-Request-Id'))->toBe($json['request_id'])
        ->and($json['error']['message'])->toBeString()->not->toBeEmpty()
        ->and(array_keys($json))->toEqualCanonicalizing(['error', 'request_id'])
        ->and(array_keys($json['error']))->toEqualCanonicalizing(['code', 'message', 'retryable']);
}

test('unauthenticated ai request returns the 401 envelope', function () {
    assertEnvelope($this->getJson('/ai/v1/_probe'), 401, 'UNAUTHENTICATED');
});

test('unauthenticated ai request without a json accept header still returns the envelope', function () {
    assertEnvelope($this->get('/ai/v1/_probe'), 401, 'UNAUTHENTICATED');
});

test('authenticated success carries request id and no-store', function () {
    $response = $this->actingAs(User::factory()->create())->getJson('/ai/v1/_probe');

    $response->assertOk()->assertJson(['ok' => true]);
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('X-Request-Id'))->toMatch('/^req_[A-Za-z0-9]+$/');
});

test('mutation without a csrf token returns the 419 envelope', function () {
    // Laravel skips the CSRF check while the app environment is "testing".
    app()->instance('env', 'local');

    $response = $this->actingAs(User::factory()->create())->postJson('/ai/v1/_probe');

    assertEnvelope($response, 419, 'CSRF_MISMATCH');
});

test('unknown route under ai/v1 returns the 404 envelope', function () {
    assertEnvelope($this->getJson('/ai/v1/does-not-exist'), 404, 'NOT_FOUND');
    assertEnvelope($this->get('/ai/v1/does-not-exist'), 404, 'NOT_FOUND');
});

test('ai exception renders its own code, status and retryable flag', function () {
    $response = $this->actingAs(User::factory()->create())->getJson('/ai/v1/_ai');

    assertEnvelope($response, 504, 'AI_TIMEOUT', true);
    $response->assertJsonPath('error.message', 'Layanan AI belum merespons. Coba lagi nanti.');
});

test('authorization failures render the 403 envelope', function () {
    $user = User::factory()->create();

    assertEnvelope($this->actingAs($user)->getJson('/ai/v1/_forbidden'), 403, 'FORBIDDEN');
    assertEnvelope($this->actingAs($user)->getJson('/ai/v1/_spatie'), 403, 'FORBIDDEN');
});

test('missing models render the 404 envelope', function () {
    assertEnvelope($this->actingAs(User::factory()->create())->getJson('/ai/v1/_missing'), 404, 'NOT_FOUND');
});

test('validation failures render the 422 envelope without field leaks', function () {
    $response = $this->actingAs(User::factory()->create())->getJson('/ai/v1/_invalid');

    assertEnvelope($response, 422, 'INVALID_INPUT');
    expect($response->json())->not->toHaveKey('errors');
});

test('unexpected throwables render 500 INTERNAL without leaking details', function () {
    $response = $this->actingAs(User::factory()->create())->getJson('/ai/v1/_boom');

    assertEnvelope($response, 500, 'INTERNAL');
    expect($response->getContent())->not->toContain('secret')
        ->and($response->getContent())->not->toContain('/tmp/trace')
        ->and($response->getContent())->not->toContain('trace');
});

test('rate limit renders the 429 envelope with Retry-After', function () {
    $user = User::factory()->create();
    $limit = config('ai.quotas.scan.per_minute');

    for ($i = 0; $i < $limit; $i++) {
        $this->actingAs($user)->getJson('/ai/v1/_limited')->assertOk();
    }
    $response = $this->actingAs($user)->getJson('/ai/v1/_limited');

    assertEnvelope($response, 429, 'RATE_LIMITED', true);
    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0);
});

test('ai limiters are keyed by user and read quotas from config', function () {
    config(['ai.quotas.scan.per_minute' => 3, 'ai.quotas.scan.per_day' => 7]);
    config(['ai.quotas.chat.per_minute' => 4, 'ai.quotas.chat.per_day' => 9]);
    $user = User::factory()->create();
    $request = Request::create('/x');
    $request->setUserResolver(fn () => $user);

    $scan = RateLimiter::limiter('ai-scan')($request);
    $chat = RateLimiter::limiter('ai-chat')($request);

    expect($scan)->toHaveCount(2)
        ->and([$scan[0]->maxAttempts, $scan[0]->decaySeconds])->toBe([3, 60])
        ->and([$scan[1]->maxAttempts, $scan[1]->decaySeconds])->toBe([7, 86400])
        ->and([$chat[0]->maxAttempts, $chat[0]->decaySeconds])->toBe([4, 60])
        ->and([$chat[1]->maxAttempts, $chat[1]->decaySeconds])->toBe([9, 86400])
        ->and($scan[0]->key)->toContain((string) $user->getAuthIdentifier())
        ->and($scan[0]->key)->not->toBe($chat[0]->key);
});

test('non-ai routes keep their existing behaviour', function () {
    $this->get('/dashboard')->assertRedirect('/login');
    $json = $this->getJson('/definitely-missing')->assertNotFound()->json();
    expect($json)->not->toHaveKey('error')
        ->and($json)->not->toHaveKey('request_id');
    $this->getJson('/dashboard')->assertUnauthorized()
        ->assertJson(['message' => 'Unauthenticated.']);
});
