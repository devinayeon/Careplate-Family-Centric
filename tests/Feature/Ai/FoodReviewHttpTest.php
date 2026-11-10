<?php

use App\Models\Food;
use App\Models\FoodRevision;
use App\Models\FoodSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/FoodSubmissionSupport.php';
require_once __DIR__.'/DbPortabilitySupport.php';

beforeEach(function () {
    chatSetUp();
    chatFake();
    fsManifest();
});

/** Submits and approves (with acknowledgement) as admin; returns [admin, submission, approve response]. */
function frApproved(array $overrides = []): array
{
    $admin = fsUser('admin');
    $submission = fsPending($overrides);
    $response = fsApprove($admin, $submission->id, fsApproveBody())->assertOk();

    return [$admin, $submission, $response];
}

test('permission matrix for approve and revoke: only admin; family head, nutritionist, guest and CSRF are refused', function () {
    $submission = fsPending();
    $food = cfFood();

    foreach (['family_head', 'nutritionist', ''] as $role) {
        chatError(fsApprove(fsUser($role), $submission->id, fsApproveBody()), 403, 'FORBIDDEN');
        chatError(fsRevoke(fsUser($role), $food->id, ['expected_revision' => 3, 'reason' => 'x']), 403, 'FORBIDDEN');
    }
    chatError(fsApprove(null, $submission->id, fsApproveBody()), 401, 'UNAUTHENTICATED');
    chatError(fsRevoke(null, $food->id, ['expected_revision' => 3, 'reason' => 'x']), 401, 'UNAUTHENTICATED');

    app()->instance('env', 'local');
    chatError(fsApprove(fsUser('admin'), $submission->id, fsApproveBody()), 419, 'CSRF_MISMATCH');
    chatError(fsRevoke(fsUser('admin'), $food->id, ['expected_revision' => 3, 'reason' => 'x']), 419, 'CSRF_MISMATCH');
    expect($submission->fresh()->status)->toBe('pending')->and($food->fresh()->status)->toBe('verified');
});

test('a user holding verify-food without the admin role is still 403', function () {
    $user = fsUser('nutritionist');
    $user->givePermissionTo('verify-food');
    $submission = fsPending();

    chatError(fsApprove($user, $submission->id, fsApproveBody()), 403, 'FORBIDDEN');
    chatError(fsRevoke($user, cfFood()->id, ['expected_revision' => 3, 'reason' => 'x']), 403, 'FORBIDDEN');
});

test('approve returns the stored contract Decision and the food is immediately searchable', function () {
    [, $submission, $response] = frApproved();

    $json = $response->json();
    // Exact key order on SQLite; MySQL hands JSON object keys back in its own order (not a contract), same key set.
    $keys = ['food_id', 'revision', 'status', 'sync_status'];
    DB::getDriverName() === 'sqlite' ? expect(array_keys($json))->toBe($keys) : expect(array_keys($json))->toEqualCanonicalizing($keys);
    expect($json['status'])->toBe('verified')->and($json['revision'])->toBe(1)->and($json['sync_status'])->toBe('ready')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
    expect($submission->fresh()->status)->toBe('approved')->and($submission->fresh()->revision_counter)->toBe(2);

    $list = $this->actingAs(fsUser('nutritionist'))->getJson('/ai/v1/foods?q=tahu')->assertOk()->json('data');
    expect(array_column($list, 'food_id'))->toBe([$json['food_id']]);
});

test('an approved food can be cited by a chat database turn', function () {
    [, , $response] = frApproved();
    [$user, $session] = chatActor();

    $json = chatOk(chatAsk($user, $session, 'Berapa protein 50 gram tahu contoh?'));

    expect($json['mode'])->toBe('database')->and($json['citations'][0]['food_id'])->toBe($response->json('food_id'))
        ->and($json['claims'][0]['derived_value'])->toBe(5);
    Http::assertNothingSent();
});

test('revoke hides the food and a new chat turn can no longer cite it', function () {
    [$admin, , $approved] = frApproved();
    $foodId = $approved->json('food_id');

    $revoked = fsRevoke($admin, $foodId, ['expected_revision' => 1, 'reason' => 'Nilai salah.'])->assertOk();

    expect(dbCanonical($revoked->json()))->toBe(dbCanonical(['food_id' => $foodId, 'revision' => 2, 'status' => 'revoked', 'sync_status' => 'ready']));
    $this->actingAs(fsUser('nutritionist'))->getJson('/ai/v1/foods?q=tahu')->assertOk()->assertExactJson(['data' => []]);
    [$user, $session] = chatActor();
    $json = chatOk(chatAsk($user, $session, 'Berapa protein 50 gram tahu contoh?', 2));
    expect(array_column($json['citations'], 'food_id'))->not->toContain($foodId)->and($json['mode'])->not->toBe('database');
});

test('approve without acknowledgement on a flagged candidate is 422 and publishes nothing', function () {
    $admin = fsUser('admin');
    $submission = fsPending();

    $r = fsApprove($admin, $submission->id, fsApproveBody(1, false));

    chatError($r, 422, 'INVALID_INPUT');
    expect($r->json('error.message'))->toContain('REVIEW_ACK_REQUIRED')
        ->and(Food::count())->toBe(0)->and($submission->fresh()->status)->toBe('pending');
    fsApprove($admin, $submission->id, ['expected_revision' => 1, 'reviewer_note' => 'ok'])->assertStatus(422);
});

test('a source missing from the manifest is accepted as pending but refused on approval', function () {
    $admin = fsUser('admin');
    $submission = fsPending(['source' => ['provider' => 'MANUAL', 'source_id' => 'warung-1', 'snapshot_version' => 'v1', 'source_sha256' => null]]);

    $r = fsApprove($admin, $submission->id, fsApproveBody());

    chatError($r, 422, 'INVALID_INPUT');
    expect($r->json('error.message'))->toContain('SOURCE_NOT_IN_MANIFEST')->and(Food::count())->toBe(0);
});

test('stale expected_revision is 409 on approve and on revoke', function () {
    $admin = fsUser('admin');
    $submission = fsPending();

    chatError(fsApprove($admin, $submission->id, fsApproveBody(5)), 409, 'STALE_REVISION');
    $foodId = fsApprove($admin, $submission->id, fsApproveBody())->json('food_id');
    chatError(fsRevoke($admin, $foodId, ['expected_revision' => 7, 'reason' => 'x']), 409, 'STALE_REVISION');
    expect(Food::query()->find($foodId)->status)->toBe('verified');
});

test('an identical approve and revoke replay returns the stored result with no new revision rows', function () {
    [$admin, $submission, $first] = frApproved();
    $rows = FoodRevision::count();

    expect(fsApprove($admin, $submission->id, fsApproveBody())->assertOk()->json())->toBe($first->json())
        ->and(FoodRevision::count())->toBe($rows);

    $foodId = $first->json('food_id');
    $body = ['expected_revision' => 1, 'reason' => 'Nilai salah.'];
    $revoked = fsRevoke($admin, $foodId, $body)->assertOk()->json();
    $rows = FoodRevision::count();
    expect(fsRevoke($admin, $foodId, $body)->assertOk()->json())->toBe($revoked)->and(FoodRevision::count())->toBe($rows);
});

test('unknown ids are 404 envelopes', function () {
    $admin = fsUser('admin');
    $id = '99999999-9999-4999-8999-999999999999';

    chatError(fsApprove($admin, $id, fsApproveBody()), 404, 'NOT_FOUND');
    chatError(fsRevoke($admin, $id, ['expected_revision' => 1, 'reason' => 'x']), 404, 'NOT_FOUND');
    chatError(fsApprove($admin, 'not-a-uuid', fsApproveBody()), 404, 'NOT_FOUND');
});

test('decision body validation: bad revision, blank text, extra keys and client authority fields', function (array $body) {
    $admin = fsUser('admin');
    $submission = fsPending();

    chatError(fsApprove($admin, $submission->id, $body + fsApproveBody()), 422, 'INVALID_INPUT');
    chatError(fsRevoke($admin, cfFood()->id, $body + ['expected_revision' => 3, 'reason' => 'x']), 422, 'INVALID_INPUT');
    expect($submission->fresh()->status)->toBe('pending');
})->with([
    'revision zero' => [['expected_revision' => 0]],
    'revision text' => [['expected_revision' => 'abc']],
    'revision float' => [['expected_revision' => 1.5]],
    'status' => [['status' => 'verified']],
    'verified_by' => [['verified_by' => 'x']],
    'actor' => [['actor_id' => 'x']],
    'ack not bool' => [['acknowledge_review_flags' => 'maybe']],
]);

test('blank, missing and oversize reviewer text is 422', function () {
    $admin = fsUser('admin');
    $submission = fsPending();
    $food = cfFood();

    foreach ([['reviewer_note' => ''], ['reviewer_note' => '   '], ['reviewer_note' => str_repeat('a', 1001)]] as $bad) {
        chatError(fsApprove($admin, $submission->id, $bad + fsApproveBody()), 422, 'INVALID_INPUT');
    }
    chatError(fsApprove($admin, $submission->id, ['expected_revision' => 1]), 422, 'INVALID_INPUT');
    chatError(fsRevoke($admin, $food->id, ['expected_revision' => 3]), 422, 'INVALID_INPUT');
    chatError(fsRevoke($admin, $food->id, ['expected_revision' => 3, 'reason' => str_repeat('a', 1001)]), 422, 'INVALID_INPUT');
});

test('the audit id is a keyed pseudonym: no email, name or raw user id is stored', function () {
    [$admin, $submission] = frApproved();

    $food = Food::query()->firstOrFail();
    $revision = FoodRevision::query()->where('food_id', $food->id)->firstOrFail();
    foreach ([$food->verifier_audit_id, $revision->actor_audit_id] as $audit) {
        expect($audit)->toMatch('/^user:[0-9a-f]{16}$/')
            ->not->toContain($admin->email)->not->toContain($admin->name)->not->toBe('user:'.$admin->id);
    }
    expect($revision->actor_id)->toBe($admin->id)->and($food->verified_by)->toBe($admin->id)
        ->and(json_encode([$revision->note, $revision->snapshot, $submission->fresh()->decision_result]))->not->toContain($admin->email);
});

test('the endpoints write no status outside ReviewFood: a pending submission stays hidden until approved', function () {
    fsPending();
    fsPending(['source' => ['source_id' => '900002']]);

    expect(Food::count())->toBe(0)->and(FoodSubmission::query()->where('status', 'pending')->count())->toBe(2);
});
