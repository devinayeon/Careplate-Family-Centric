<?php

use App\Actions\Foods\ReviewFood;
use App\Exceptions\Ai\AiException;
use App\Models\Food;
use App\Models\FoodSubmission;
use App\Services\Ai\Foods\SnapshotManifest;
use App\Services\Ai\Foods\UsdaFoundationImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/TwoConnectionMysqlSupport.php';
require_once __DIR__.'/../FoodSubmissionSupport.php';
require_once __DIR__.'/../FoundationFixture.php';

// Catalog side of spec 06 section 4 on real InnoDB (REPEATABLE READ), same harness as TwoConnectionMysqlChatTest:
// B runs from a DB::listen hook while A is inside its transaction; a B that needs A's row fails after 1 s and must
// be a retryable DB_BUSY. Never two successes, never two revisions, never a raw driver error.

beforeEach(function () {
    $this->cmDefault = DB::getDefaultConnection();
    $skip = cmBoot();
    if ($skip !== null) {
        test()->markTestSkipped($skip);
    }
    test()->travelTo('2026-11-08 10:00:00');
    $this->manifest = rfManifest();
});

afterEach(fn () => cmShutdown($this->cmDefault));

function cmReview(): ReviewFood
{
    return new ReviewFood(test()->manifest);
}

/** A verified food at revision 3 with the same source triple as rfSubmission(). */
function cmSameTripleFood(): Food
{
    return cfFood(['source_provider' => 'USDA_FDC', 'source_id' => '900001', 'snapshot_version' => '2099-12-31', 'current_revision' => 3]);
}

test('two approves with the same expected revision: B blocked on A\'s submission lock is DB_BUSY, then STALE_REVISION', function () {
    $submission = rfSubmission(['revision_counter' => 2]);
    $probe = cmAfter('cc_a', 'select * from `food_submissions`', fn () => ccOn('cc_b', fn () => cmReview()->approve($submission->id, null, 'audit-b', 2, 'Setuju B')));

    $first = cmReview()->approve($submission->id, null, 'audit-a', 2, 'Setuju A');
    $retry = rfThrown(fn () => ccOn('cc_b', fn () => cmReview()->approve($submission->id, null, 'audit-b', 2, 'Setuju B')));

    expect($probe->ran)->toBeTrue()
        ->and($probe->outcome)->toBeInstanceOf(AiException::class)
        ->and($probe->outcome->errorCode)->toBe('DB_BUSY')->and($probe->outcome->retryable)->toBeTrue()
        ->and($first['status'])->toBe('verified')->and($first['revision'])->toBe(1)
        ->and($retry->errorCode)->toBe('STALE_REVISION')
        ->and(DB::connection('cc_b')->table('foods')->count())->toBe(1)
        ->and(DB::connection('cc_b')->table('food_revisions')->count())->toBe(1);
});

test('B whose transaction read before A committed still sees A\'s decision: STALE_REVISION, nothing written', function (string $action) {
    $submission = rfSubmission(['revision_counter' => 2]);
    $food = cfFood(['current_revision' => 3]);
    $decide = fn (string $audit) => $action === 'approve'
        ? cmReview()->approve($submission->id, null, $audit, 2, 'Setuju')
        : cmReview()->revoke($food->id, null, $audit, 3, 'Alasan');
    $b = DB::connection('cc_b');
    $b->beginTransaction();
    $b->table('food_submissions')->count(); // B's REPEATABLE READ snapshot starts here, before A commits

    $first = $decide('audit-a');
    $second = cmCaught(fn () => ccOn('cc_b', fn () => $decide('audit-b')));
    $b->rollBack();

    expect($first['revision'])->toBeInt()
        ->and($second)->toBeInstanceOf(AiException::class)
        ->and($second?->errorCode)->toBe('STALE_REVISION')
        ->and(DB::connection('cc_a')->table('food_revisions')->count())->toBe(1);
})->with(['approve', 'revoke']);

test('approve vs revoke on one food: the side blocked on the food row lock is DB_BUSY; one decision, one revision', function (string $holder) {
    $food = cmSameTripleFood();
    $submission = rfSubmission(['revision_counter' => 2]);
    $approve = fn (string $audit) => cmReview()->approve($submission->id, null, $audit, 2, 'Setuju');
    $revoke = fn (string $audit) => cmReview()->revoke($food->id, null, $audit, 3, 'Alasan');
    [$a, $b] = $holder === 'approve' ? [$approve, $revoke] : [$revoke, $approve];
    $probe = cmAfter('cc_a', 'select * from `foods`', fn () => ccOn('cc_b', fn () => $b('audit-b')));

    $first = $a('audit-a');

    expect($probe->ran)->toBeTrue()
        ->and($probe->outcome)->toBeInstanceOf(AiException::class)
        ->and($probe->outcome->errorCode)->toBe('DB_BUSY')
        ->and($first['revision'])->toBe(4)
        ->and($first['status'])->toBe($holder === 'approve' ? 'verified' : 'revoked')
        ->and((int) DB::connection('cc_b')->table('foods')->value('current_revision'))->toBe(4)
        ->and(DB::connection('cc_b')->table('food_revisions')->pluck('actor_audit_id')->all())->toBe(['audit-a']);
})->with(['approve', 'revoke']);

test('a lock wait timeout in approve, revoke or a submission insert is a retryable DB_BUSY, not a 500', function () {
    $food = cmSameTripleFood();
    $submission = rfSubmission(['revision_counter' => 2]);
    fsManifest();
    $user = fsUser();
    $blocker = DB::connection('cc_b');
    $blocker->beginTransaction();
    $blocker->table('foods')->where('id', $food->id)->lockForUpdate()->first();
    $blocker->table('food_submissions')->insert(['id' => (string) Str::uuid(), 'status' => 'pending', 'revision_counter' => 1,
        'candidate' => '{}', 'source_provider' => 'MANUAL', 'source_id' => '900001', 'snapshot_version' => '2099-12-31']);

    $approve = cmCaught(fn () => cmReview()->approve($submission->id, null, 'audit-a', 2, 'Setuju'));
    $revoke = cmCaught(fn () => cmReview()->revoke($food->id, null, 'audit-a', 3, 'Alasan'));
    $post = fsSubmit($user, fsBody());
    $blocker->rollBack();

    foreach ([$approve, $revoke] as $error) {
        expect($error)->toBeInstanceOf(AiException::class)
            ->and($error->errorCode)->toBe('DB_BUSY')->and($error->retryable)->toBeTrue();
    }
    chatError($post, 503, 'DB_BUSY', true);
    expect(DB::connection('cc_a')->table('food_revisions')->count())->toBe(0)
        ->and(DB::connection('cc_a')->table('food_submissions')->count())->toBe(1)
        ->and((int) DB::connection('cc_a')->table('foods')->value('current_revision'))->toBe(3);
});

test('an approve committed between importer reads is never overwritten by --refresh-pending', function () {
    $snapshot = synSnapshot();
    $path = sys_get_temp_dir().'/cm-manifest-'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($path, json_encode(['schema_version' => SnapshotManifest::SCHEMA, 'snapshots' => [[
        'source_provider' => 'USDA_FDC', 'snapshot_version' => $snapshot['version'], 'data_type' => 'Foundation',
        'url' => $snapshot['url'], 'sha256' => $snapshot['sha256'], 'retrieved_at' => $snapshot['retrieved_at'],
        'record_count' => 1, 'raw_json_file' => 'x.json', 'imported_source_ids' => ['900009'],
    ]]]));
    app(UsdaFoundationImporter::class)->import(synCuration(), synFoods(), $snapshot);
    $potato = FoodSubmission::query()->where('source_id', '900009')->sole();
    $foods = synFoods();
    $foods[0]['foodNutrients'][0]['amount'] = 8.5; // raw rice protein (FOOD-002, still pending)
    $foods[8]['foodNutrients'][0]['amount'] = 3.5; // russet potato protein (FOOD-009, approved by B mid-import)
    $approve = fn () => ccOn('cc_b', fn () => (new ReviewFood(new SnapshotManifest($path)))->approve($potato->id, null, 'audit-b', 1, 'Setuju', true));
    $probe = cmAfter('cc_a', 'select * from `food_submissions`', $approve); // FOOD-001, the first curation row

    $report = app(UsdaFoundationImporter::class)->import(synCuration(), $foods, $snapshot, 20, true);
    $after = DB::connection('cc_b')->table('food_submissions')->where('id', $potato->id)->first();

    expect($probe->outcome)->toBeArray()->and($probe->outcome['status'])->toBe('verified')
        ->and(collect($report['rows'])->firstWhere('candidate_id', 'FOOD-009')['status'])->toBe('changed')
        ->and(collect($report['rows'])->firstWhere('candidate_id', 'FOOD-002')['status'])->toBe('refreshed')
        ->and($after->status)->toBe('approved')
        ->and((int) $after->revision_counter)->toBe(2)
        ->and(json_decode($after->candidate, true))->toEqual($potato->candidate);
});
