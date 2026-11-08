<?php

use App\Actions\Foods\ReviewFood;
use App\Models\Food;
use App\Models\FoodRevision;
use App\Models\FoodSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/ReviewFoodSupport.php';
require_once __DIR__.'/DbPortabilitySupport.php';

beforeEach(fn () => rfManifest());

function rfApprove(FoodSubmission $s, array $args = []): array
{
    $a = array_merge(['actor' => null, 'audit' => 'script:test', 'expected' => 2, 'note' => 'Sumber dicocokkan.', 'ack' => false], $args);

    return app(ReviewFood::class)->approve($s->id, $a['actor'], $a['audit'], $a['expected'], $a['note'], $a['ack']);
}

it('approves a submission: food becomes visible to Food::verified with a search_text and an audit trail', function () {
    $actor = User::factory()->create();
    $submission = rfSubmission();

    $result = rfApprove($submission, ['actor' => $actor, 'audit' => 'user:admin']);

    $food = Food::verified()->sole();
    expect($result)->toBe(['food_id' => $food->id, 'revision' => 1, 'status' => 'verified', 'sync_status' => 'ready'])
        ->and($food->name)->toBe('Beras putih mentah')
        ->and($food->current_revision)->toBe(1)
        ->and($food->energy_method)->toBe('Atwater specific')
        ->and($food->source_sha256)->toBe(RF_SHA)
        ->and($food->verifier_audit_id)->toBe('user:admin')
        ->and($food->verified_by)->toBe($actor->id)
        ->and($food->verified_at)->not->toBeNull()
        ->and($food->search_text)->toContain('beras putih mentah')->toContain('rice white long grain unenriched raw');

    $revision = FoodRevision::sole();
    expect($revision->food_id)->toBe($food->id)
        ->and([$revision->revision, $revision->action, $revision->status])->toBe([1, 'approve', 'verified'])
        ->and($revision->actor_id)->toBe($actor->id)
        ->and($revision->actor_audit_id)->toBe('user:admin')
        ->and($revision->note)->toBe('Sumber dicocokkan.')
        ->and($revision->decision_hash)->toMatch('/^[0-9a-f]{64}$/')
        ->and(dbCanonical($revision->result))->toBe(dbCanonical($result))
        ->and($revision->snapshot['status'])->toBe('verified')
        ->and($revision->snapshot['source']['id'])->toBe('900001')
        ->and($revision->snapshot['facts']['nutrients']['sugars_total_g'])->toBeNull();

    $submission->refresh();
    expect($submission->status)->toBe('approved')
        ->and($submission->food_id)->toBe($food->id)
        ->and($submission->revision_counter)->toBe(3)
        ->and(dbCanonical($submission->decision_result))->toBe(dbCanonical($result))
        ->and($submission->decision_hash)->toBe($revision->decision_hash)
        ->and($submission->decided_by)->toBe($actor->id)
        ->and($submission->decided_at)->not->toBeNull();
});

it('keeps unknown nutrients null and stores known ones as decimal strings', function () {
    rfApprove(rfSubmission());

    $food = Food::sole();
    expect($food->sugars_total_g)->toBeNull()
        ->and(DB::table('foods')->whereNull('sugars_total_g')->count())->toBe(1)
        ->and($food->protein_g)->toBe('7.1000')
        ->and($food->sodium_mg)->toBe('1.0000');
});

it('revokes: hides the food, clears search_text, bumps the revision and appends an audit row', function () {
    $food = Food::find(rfApprove(rfSubmission())['food_id']);

    $result = app(ReviewFood::class)->revoke($food->id, null, 'user:admin', 1, 'Nilai salah.');

    $food->refresh();
    expect($result)->toBe(['food_id' => $food->id, 'revision' => 2, 'status' => 'revoked', 'sync_status' => 'ready'])
        ->and(Food::verified()->count())->toBe(0)
        ->and($food->status)->toBe('revoked')
        ->and($food->search_text)->toBeNull()
        ->and($food->current_revision)->toBe(2);
    $row = FoodRevision::where('revision', 2)->sole();
    expect([$row->action, $row->status, $row->note, $row->actor_audit_id])->toBe(['revoke', 'revoked', 'Nilai salah.', 'user:admin'])
        ->and(dbCanonical($row->result))->toBe(dbCanonical($result))
        ->and($row->decision_hash)->toMatch('/^[0-9a-f]{64}$/')
        ->and(FoodRevision::count())->toBe(2);
});

it('refuses stale revisions with 409 STALE_REVISION and changes nothing', function () {
    $submission = rfSubmission();
    $e = rfThrown(fn () => rfApprove($submission, ['expected' => 1]));
    expect([$e->errorCode, $e->status])->toBe(['STALE_REVISION', 409])
        ->and(Food::count())->toBe(0)
        ->and($submission->fresh()->status)->toBe('pending');

    $foodId = rfApprove($submission)['food_id'];
    $e = rfThrown(fn () => app(ReviewFood::class)->revoke($foodId, null, 'script:test', 0, 'x'));
    expect([$e->errorCode, $e->status])->toBe(['STALE_REVISION', 409])
        ->and(Food::verified()->count())->toBe(1);
});

it('replays an identical approve and revoke from the stored result without new revision rows', function () {
    $submission = rfSubmission();
    $first = rfApprove($submission);
    $again = rfApprove($submission);

    expect($again)->toBe($first)
        ->and(FoodRevision::count())->toBe(1)
        ->and($submission->fresh()->revision_counter)->toBe(3);

    $revoked = app(ReviewFood::class)->revoke($first['food_id'], null, 'script:test', 1, 'Salah.');
    $replay = app(ReviewFood::class)->revoke($first['food_id'], null, 'script:test', 1, 'Salah.');
    expect($replay)->toBe($revoked)->and(FoodRevision::count())->toBe(2);
});

it('treats a different decision at the original revision as stale', function () {
    $submission = rfSubmission();
    rfApprove($submission);
    $e = rfThrown(fn () => rfApprove($submission, ['note' => 'Catatan lain.']));
    expect($e->errorCode)->toBe('STALE_REVISION')->and(FoodRevision::count())->toBe(1);

    $other = User::factory()->create();
    expect(rfThrown(fn () => rfApprove($submission, ['actor' => $other]))->errorCode)->toBe('STALE_REVISION');
});

it('rolls back everything when a write fails mid-transaction', function () {
    $submission = rfSubmission();
    FoodRevision::creating(function () {
        throw new RuntimeException('forced failure');
    });

    expect(fn () => rfApprove($submission))->toThrow(RuntimeException::class, 'forced failure');

    expect(Food::count())->toBe(0)
        ->and(FoodRevision::count())->toBe(0)
        ->and($submission->fresh()->status)->toBe('pending')
        ->and($submission->fresh()->revision_counter)->toBe(2)
        ->and($submission->fresh()->decision_hash)->toBeNull();
});

it('rolls a revoke back too', function () {
    $foodId = rfApprove(rfSubmission())['food_id'];
    FoodRevision::creating(function () {
        throw new RuntimeException('forced failure');
    });

    expect(fn () => app(ReviewFood::class)->revoke($foodId, null, 'script:test', 1, 'x'))->toThrow(RuntimeException::class);

    $food = Food::find($foodId);
    expect($food->status)->toBe('verified')->and($food->current_revision)->toBe(1)->and($food->search_text)->not->toBeNull();
});

it('refuses a source that is not in the snapshot manifest or whose sha differs', function () {
    $unknown = rfSubmission(['source_id' => '123456']);
    $e = rfThrown(fn () => rfApprove($unknown));
    expect([$e->errorCode, $e->status])->toBe(['INVALID_INPUT', 422])->and($e->getMessage())->toContain('SOURCE_NOT_IN_MANIFEST');

    $wrongSha = rfSubmission(['source_id' => '900002', 'source_sha256' => str_repeat('b', 64)]);
    $e = rfThrown(fn () => rfApprove($wrongSha));
    expect($e->errorCode)->toBe('INVALID_INPUT')->and($e->getMessage())->toContain('SOURCE_SHA_MISMATCH');

    $nullSha = rfSubmission(['source_id' => '900003', 'source_sha256' => null]);
    expect(rfThrown(fn () => rfApprove($nullSha))->getMessage())->toContain('SOURCE_SHA_MISMATCH')
        ->and(Food::count())->toBe(0)
        ->and(FoodSubmission::where('status', 'pending')->count())->toBe(3);
});

it('refuses a flagged candidate without acknowledgement and accepts it with one', function () {
    $submission = rfSubmission([], ['requires_review' => true, 'review_reasons' => ['variant_pick']]);

    $e = rfThrown(fn () => rfApprove($submission));
    expect([$e->errorCode, $e->status])->toBe(['INVALID_INPUT', 422])
        ->and($e->getMessage())->toContain('REVIEW_ACK_REQUIRED')
        ->and($e->getMessage())->not->toContain('variant_pick')
        ->and(Food::count())->toBe(0);

    $result = rfApprove($submission, ['ack' => true]);
    expect($result['status'])->toBe('verified')->and(Food::verified()->count())->toBe(1);
});

it('rejects invalid candidates', function (array $override) {
    $submission = rfSubmission([], $override);
    $e = rfThrown(fn () => rfApprove($submission));
    expect($e->errorCode)->toBe('INVALID_INPUT')->and($e->getMessage())->toContain('CANDIDATE_INVALID')
        ->and(Food::count())->toBe(0);
})->with([
    'negative nutrient' => [['nutrients' => ['protein_g' => '-1']]],
    'non numeric nutrient' => [['nutrients' => ['fat_g' => 'abc']]],
    'float nutrient' => [['nutrients' => ['sodium_mg' => 1.5]]],
    'wrong basis' => [['basis' => '1 serving']],
    'empty name' => [['name' => '  ']],
    'aliases not a list of strings' => [['aliases' => 'beras']],
]);

it('refuses unknown submissions and foods with 404 NOT_FOUND', function () {
    expect(rfThrown(fn () => app(ReviewFood::class)->approve('nope', null, 'a', 1, 'n'))->status)->toBe(404)
        ->and(rfThrown(fn () => app(ReviewFood::class)->revoke('nope', null, 'a', 1, 'n'))->status)->toBe(404);
});

it('refuses a blank note or reason', function () {
    $submission = rfSubmission();
    expect(rfThrown(fn () => rfApprove($submission, ['note' => '  ']))->errorCode)->toBe('INVALID_INPUT');
    $foodId = rfApprove($submission)['food_id'];
    expect(rfThrown(fn () => app(ReviewFood::class)->revoke($foodId, null, 'a', 1, ' '))->errorCode)->toBe('INVALID_INPUT');
});
