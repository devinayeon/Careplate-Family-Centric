<?php

use App\Models\User;
use Database\Seeders\AiPermissionSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;

require_once __DIR__.'/ChatFoodSupport.php';

beforeEach(fn () => test()->seed(AiPermissionSeeder::class));

function foodsUser(string $role = 'nutritionist'): User
{
    $user = User::factory()->create();
    if ($role !== '') {
        $user->assignRole($role);
    }

    return $user;
}

function foodsGet(?User $user, string $query = ''): TestResponse
{
    $test = $user === null ? test() : test()->actingAs($user);

    return $test->getJson('/ai/v1/foods'.$query);
}

test('unauthenticated is 401 and a user without nutrition-chat is 403, both as envelopes', function () {
    foodsGet(null)->assertStatus(401)->assertJsonPath('error.code', 'UNAUTHENTICATED');
    $r = foodsGet(foodsUser(''))->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');

    expect(array_keys($r->json()))->toEqualCanonicalizing(['error', 'request_id'])
        ->and($r->headers->get('Cache-Control'))->toContain('no-store');
});

test('every role that owns nutrition-chat gets 200 with no-store and a request id', function (string $role) {
    $r = foodsGet(foodsUser($role))->assertOk();

    expect($r->headers->get('Cache-Control'))->toContain('no-store')
        ->and($r->headers->get('X-Request-Id'))->toStartWith('req_')
        ->and($r->json())->toBe(['data' => []]);
})->with(['admin', 'family_head', 'nutritionist']);

test('an empty q lists up to five verified foods by name then id with the source summary', function () {
    foreach (['Wortel', 'Apel', 'Tahu', 'Kacang', 'Beras', 'Jagung', 'Ubi'] as $name) {
        cfFood(['name' => $name, 'source_id' => "id-$name", 'snapshot_version' => '2026-10-29', 'current_revision' => 2]);
    }

    $json = foodsGet(foodsUser())->assertOk()->json();

    expect(array_column($json['data'], 'name'))->toBe(['Apel', 'Beras', 'Jagung', 'Kacang', 'Tahu'])
        ->and(array_keys($json))->toBe(['data'])
        ->and(array_keys($json['data'][0]))->toBe(['food_id', 'revision', 'name', 'basis', 'verification_level', 'source_reference'])
        ->and($json['data'][0]['revision'])->toBe(2)
        ->and($json['data'][0]['basis'])->toBe('100g')
        ->and($json['data'][0]['verification_level'])->toBe('source_reviewed')
        ->and($json['data'][0]['source_reference'])->toContain('USDA FoodData Central', 'id-Apel', '2026-10-29');
});

test('the response carries no nutrient values, internals or non-verified rows', function () {
    cfFood(['name' => 'Tahu contoh']);
    cfFood(['name' => 'Tahu pending', 'status' => 'pending']);
    cfFood(['name' => 'Tahu cabut', 'status' => 'revoked', 'search_text' => null]);

    $r = foodsGet(foodsUser(), '?q=tahu')->assertOk();

    expect(array_column($r->json('data'), 'name'))->toBe(['Tahu contoh'])
        ->and($r->getContent())->not->toContain('protein')->not->toContain('search_text')->not->toContain('pending')->not->toContain('revoked');
});

test('q matches names and aliases case-insensitively and a wildcard matches nothing extra', function () {
    cfFood(['name' => 'Tahu contoh', 'aliases' => ['tahu contoh', 'tofu']]);
    cfFood(['name' => 'Tempe']);
    $user = foodsUser();

    expect(array_column(foodsGet($user, '?q=TOFU')->json('data'), 'name'))->toBe(['Tahu contoh'])
        ->and(foodsGet($user, '?q=%25')->json('data'))->toBe([])
        ->and(foodsGet($user, '?q=t_mpe')->json('data'))->toBe([])
        ->and(foodsGet($user, '?q=zzz')->json('data'))->toBe([]);
});

test('limit is 1 to 5', function () {
    foreach (range(1, 6) as $i) {
        cfFood(['name' => "Buah $i"]);
    }
    $user = foodsUser();

    expect(foodsGet($user, '?limit=2')->json('data'))->toHaveCount(2)
        ->and(foodsGet($user, '?limit=5')->json('data'))->toHaveCount(5)
        ->and(foodsGet($user, '?limit=1')->json('data'))->toHaveCount(1);
    foreach (['0', '6', '-1', 'abc', '2.5', '99'] as $bad) {
        foodsGet($user, '?limit='.$bad)->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');
    }
});

test('q longer than 200 characters is INVALID_INPUT and 200 is fine', function () {
    $user = foodsUser();

    foodsGet($user, '?q='.str_repeat('a', 201))->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');
    foodsGet($user, '?q='.str_repeat('a', 200))->assertOk();
    foodsGet($user, '?q[]=a')->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');
});

test('the endpoint never calls the model provider', function () {
    Http::fake();
    cfFood(['name' => 'Tahu contoh']);

    foodsGet(foodsUser(), '?q=tahu')->assertOk();

    Http::assertNothingSent();
});

test('a database failure is 503 KNOWLEDGE_UNAVAILABLE, never an empty list', function () {
    Schema::drop('foods');

    $r = foodsGet(foodsUser(), '?q=tahu');

    $r->assertStatus(503)->assertJsonPath('error.code', 'KNOWLEDGE_UNAVAILABLE')->assertJsonPath('error.retryable', true);
    expect($r->getContent())->not->toContain('SQLSTATE')->not->toContain('foods');
});
