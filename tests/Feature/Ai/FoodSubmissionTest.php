<?php

use App\Models\Food;
use App\Models\FoodSubmission;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/FoodSubmissionSupport.php';

beforeEach(function () {
    rfManifest();
    Http::fake();
});

test('permission matrix: family head and admin submit, nutritionist 403, guest 401', function () {
    fsSubmit(fsUser('family_head'), fsBody())->assertCreated();
    fsSubmit(fsUser('admin'), fsBody(['source' => ['source_id' => '900002']]))->assertCreated();
    chatError(fsSubmit(fsUser('nutritionist'), fsBody()), 403, 'FORBIDDEN');
    chatError(fsSubmit(fsUser(''), fsBody()), 403, 'FORBIDDEN');
    chatError(fsSubmit(null, fsBody()), 401, 'UNAUTHENTICATED');
    expect(FoodSubmission::count())->toBe(2);
});

test('a mutation without a csrf token is 419 and stores nothing', function () {
    app()->instance('env', 'local');

    chatError(fsSubmit(fsUser(), fsBody()), 419, 'CSRF_MISMATCH');
    expect(FoodSubmission::count())->toBe(0);
});

test('201 returns the contract Submission and stores a pending row owned by the caller', function () {
    $user = fsUser();
    $response = fsSubmit($user, fsBody())->assertCreated();

    expect(array_keys($response->json()))->toBe(['submission_id', 'status', 'revision_counter'])
        ->and($response->json('status'))->toBe('pending')->and($response->json('revision_counter'))->toBe(1)
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
    $row = FoodSubmission::query()->findOrFail($response->json('submission_id'));
    expect($row->user_id)->toBe($user->id)->and($row->status)->toBe('pending')->and($row->revision_counter)->toBe(1)
        ->and($row->source_sha256)->toBe(RF_SHA)->and($row->food_id)->toBeNull()
        ->and($row->candidate['requires_review'])->toBeTrue()->and($row->candidate['review_reasons'])->toBe(['user_submission'])
        ->and($row->candidate['basis'])->toBe('100g')->and($row->candidate['name'])->toBe('Tahu contoh')
        ->and($row->candidate['nutrients'])->toBe([
            'energy_kcal' => '80', 'protein_g' => '10', 'fat_g' => '4.8', 'carbohydrate_g' => '1.9',
            'sodium_mg' => '7', 'sugars_total_g' => null, 'energy_method' => null,
        ]);
});

test('a pending submission creates no food and GET /ai/v1/foods does not return it', function () {
    $user = fsUser('nutritionist');
    fsPending();

    expect(Food::count())->toBe(0);
    $this->actingAs($user)->getJson('/ai/v1/foods?q=tahu')->assertOk()->assertExactJson(['data' => []]);
});

test('the server never fetches the source url', function () {
    fsSubmit(fsUser(), fsBody(['source' => ['source_url' => 'https://example.test/never-fetched']]))->assertCreated();

    Http::assertNothingSent();
});

test('client supplied server-owned fields are rejected at every level', function (array $extra) {
    $r = fsSubmit(fsUser(), array_replace_recursive(fsBody(), $extra));

    chatError($r, 422, 'INVALID_INPUT');
    expect(FoodSubmission::count())->toBe(0);
})->with([
    'status' => [['status' => 'approved']],
    'verified_by' => [['verified_by' => 'x']],
    'revision' => [['revision' => 9]],
    'revision_counter' => [['revision_counter' => 9]],
    'food_id' => [['food_id' => '10000000-0000-4000-8000-000000000001']],
    'user_id' => [['user_id' => 1]],
    'decision' => [['decision' => 'approve']],
    'nested source status' => [['source' => ['status' => 'verified']]],
    'nested nutrients extra' => [['nutrients' => ['fiber_g' => 1]]],
]);

test('validation rejections', function (string $field, array $body) {
    chatError(fsSubmit(fsUser(), $body), 422, 'INVALID_INPUT');
    expect(FoodSubmission::count())->toBe(0);
})->with(function () {
    $cases = [
        'basis 100ml' => ['basis' => '100ml'],
        'basis missing' => ['basis' => null],
        'name empty' => ['name' => ''],
        'name too long' => ['name' => str_repeat('a', 121)],
        'aliases not array' => ['aliases' => 'tahu'],
        'too many aliases' => ['aliases' => array_fill(0, 9, 'a')],
        'alias too long' => ['aliases' => [str_repeat('a', 81)]],
        'alias empty' => ['aliases' => ['']],
        'negative nutrient' => ['nutrients' => ['protein_g' => -1]],
        'string nutrient' => ['nutrients' => ['protein_g' => 'banyak']],
        'exponent nutrient' => ['nutrients' => ['protein_g' => '1e3']],
        'too many decimals' => ['nutrients' => ['protein_g' => 1.23456]],
        'huge nutrient' => ['nutrients' => ['sodium_mg' => 100001]],
        'bool nutrient' => ['nutrients' => ['fat_g' => true]],
        'url javascript' => ['source' => ['source_url' => 'javascript:alert(1)']],
        'url file' => ['source' => ['source_url' => 'file:///etc/passwd']],
        'url ftp' => ['source' => ['source_url' => 'ftp://example.test/x']],
        'url credentials' => ['source' => ['source_url' => 'https://user:pw@example.test/x']],
        'url oversize' => ['source' => ['source_url' => 'https://example.test/'.str_repeat('a', 500)]],
        'provider unknown' => ['source' => ['provider' => 'WIKI']],
        'source_id empty' => ['source' => ['source_id' => '']],
        'source_id oversize' => ['source' => ['source_id' => str_repeat('1', 101)]],
        'version oversize' => ['source' => ['snapshot_version' => str_repeat('1', 101)]],
        'sha not hex' => ['source' => ['source_sha256' => str_repeat('z', 64)]],
        'sha short' => ['source' => ['source_sha256' => 'abc']],
    ];
    foreach ($cases as $label => $override) {
        yield $label => [$label, array_replace_recursive(fsBody(), $override)];
    }
    $body = fsBody();
    unset($body['nutrients']['sodium_mg']);
    yield 'nutrient key missing' => ['missing', $body];
    $body = fsBody();
    unset($body['source']['source_sha256']);
    yield 'sha key missing' => ['missing', $body];
});

test('nulls and zero are valid nutrients and unknown stays null', function () {
    $r = fsSubmit(fsUser(), fsBody(['nutrients' => ['sodium_mg' => 0, 'protein_g' => null, 'energy_kcal' => '12.5']]))->assertCreated();

    $n = FoodSubmission::query()->findOrFail($r->json('submission_id'))->candidate['nutrients'];
    expect($n['sodium_mg'])->toBe('0')->and($n['protein_g'])->toBeNull()->and($n['energy_kcal'])->toBe('12.5');
});

test('a source not in the manifest is accepted as pending', function () {
    fsSubmit(fsUser(), fsBody(['source' => ['provider' => 'MANUAL', 'source_id' => 'warung-1', 'snapshot_version' => 'v1', 'source_sha256' => null]]))
        ->assertCreated()->assertJsonPath('status', 'pending');
});

test('duplicate triple: same user identical retry returns the existing submission, anything else is 409', function () {
    $user = fsUser();
    $first = fsSubmit($user, fsBody())->assertCreated();

    $again = fsSubmit($user, fsBody())->assertOk();
    expect($again->json())->toBe($first->json())->and(FoodSubmission::count())->toBe(1);

    chatError(fsSubmit($user, fsBody(['nutrients' => ['protein_g' => 11]])), 409, 'IDEMPOTENCY_CONFLICT');
    $other = fsUser('family_head');
    $r = fsSubmit($other, fsBody());
    chatError($r, 409, 'IDEMPOTENCY_CONFLICT');
    expect(json_encode($r->json()))->not->toContain($first->json('submission_id'))->and(FoodSubmission::count())->toBe(1);
});

test('submitting a triple that an importer left pending is 409 and does not leak it', function () {
    rfSubmission(['revision_counter' => 1]);

    chatError(fsSubmit(fsUser(), fsBody()), 409, 'IDEMPOTENCY_CONFLICT');
});
