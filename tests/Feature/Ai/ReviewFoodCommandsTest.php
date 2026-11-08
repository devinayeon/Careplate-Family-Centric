<?php

use App\Models\Food;
use App\Models\FoodRevision;
use App\Models\FoodSubmission;
use App\Models\User;
use Database\Seeders\AiMvpFoodSeeder;

require_once __DIR__.'/ReviewFoodSupport.php';

beforeEach(fn () => rfManifest());

it('ai:review-food approves a flagged submission only with --acknowledge-flags, then revokes', function () {
    $user = User::factory()->create();
    $submission = rfSubmission([], ['requires_review' => true, 'review_reasons' => ['variant_pick']]);
    $base = ['action' => 'approve', '--submission' => $submission->id, '--reviewer-email' => $user->email, '--audit-id' => 'cli:test', '--note' => 'Dicocokkan.', '--expected-revision' => 2];

    $this->artisan('ai:review-food', $base)->expectsOutputToContain('REVIEW_ACK_REQUIRED')->assertFailed();
    expect(Food::count())->toBe(0);

    $this->artisan('ai:review-food', $base + ['--acknowledge-flags' => true])->expectsOutputToContain('verified')->assertSuccessful();
    $food = Food::verified()->sole();
    expect($food->verified_by)->toBe($user->id);

    $this->artisan('ai:review-food', ['action' => 'revoke', '--food' => $food->id, '--audit-id' => 'cli:test', '--reason' => 'Salah.', '--expected-revision' => 1])
        ->expectsOutputToContain('revoked')->assertSuccessful();
    expect(Food::verified()->count())->toBe(0);
});

it('ai:review-food reports stale revisions and unknown reviewers without writing', function () {
    $submission = rfSubmission();
    $this->artisan('ai:review-food', ['action' => 'approve', '--submission' => $submission->id, '--note' => 'x', '--expected-revision' => 1])
        ->expectsOutputToContain('STALE_REVISION')->assertFailed();
    $this->artisan('ai:review-food', ['action' => 'approve', '--submission' => $submission->id, '--note' => 'x', '--reviewer-email' => 'nobody@example.test'])
        ->assertFailed();
    $this->artisan('ai:review-food', ['action' => 'bogus'])->assertFailed();
    expect(Food::count())->toBe(0);
});

it('ai:seed-catalog approves only pending USDA submissions with an honest audit trail and is idempotent', function () {
    $a = rfSubmission(['source_id' => '900001'], ['requires_review' => true, 'review_reasons' => ['variant_pick', 'exact_zero:sodium_mg']]);
    $b = rfSubmission(['source_id' => '900002']);
    $rejected = rfSubmission(['source_id' => '900003', 'status' => 'rejected']);
    $other = rfSubmission(['source_provider' => 'tkpi', 'source_id' => 'AP001']);

    $this->artisan('ai:seed-catalog')->expectsOutputToContain('approved 2')->assertSuccessful();

    expect(Food::verified()->count())->toBe(2)
        ->and($a->fresh()->status)->toBe('approved')
        ->and($b->fresh()->status)->toBe('approved')
        ->and($rejected->fresh()->status)->toBe('rejected')
        ->and($other->fresh()->status)->toBe('pending');
    $revision = FoodRevision::where('food_id', $a->fresh()->food_id)->sole();
    expect($revision->actor_id)->toBeNull()
        ->and($revision->actor_audit_id)->toBe('script:ai-source-match')
        ->and($revision->note)->toContain('variant_pick')->toContain('exact_zero:sodium_mg')
        ->and($revision->note)->toContain('source id and archive sha256 matched against the snapshot manifest; nutrient values NOT re-verified against the raw data; review flags acknowledged by script; no human nutritionist review')->not->toContain('checked programmatically');

    $this->artisan('ai:seed-catalog')->expectsOutputToContain('approved 0')->assertSuccessful();
    expect(FoodRevision::count())->toBe(2)->and(Food::count())->toBe(2);
});

it('ai:seed-catalog reports a refused submission and keeps going', function () {
    rfSubmission(['source_id' => '111111']);
    $ok = rfSubmission(['source_id' => '900002']);

    $this->artisan('ai:seed-catalog')->expectsOutputToContain('SOURCE_NOT_IN_MANIFEST')->expectsOutputToContain('failed 1')->assertFailed();

    expect($ok->fresh()->status)->toBe('approved')->and(Food::verified()->count())->toBe(1);
});

it('AiMvpFoodSeeder runs the same flow', function () {
    rfSubmission();
    $this->seed(AiMvpFoodSeeder::class);

    expect(Food::verified()->count())->toBe(1)
        ->and(FoodRevision::sole()->actor_audit_id)->toBe('script:ai-source-match')
        ->and(FoodSubmission::where('status', 'pending')->count())->toBe(0);
});
