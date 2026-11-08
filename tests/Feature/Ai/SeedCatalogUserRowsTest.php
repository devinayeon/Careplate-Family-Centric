<?php

use App\Models\Food;
use App\Models\FoodSubmission;
use Database\Seeders\AiMvpFoodSeeder;

require_once __DIR__.'/FoodSubmissionSupport.php';

// A user row must never be bulk-published by the demo seeding flow, whatever provider it claims.

beforeEach(fn () => rfManifest());

test('ai:seed-catalog and AiMvpFoodSeeder never touch pending user rows, any provider, even a forged USDA one', function () {
    $user = fsUser();
    $importer = rfSubmission(['source_id' => '900001']);
    // Forged rows (the endpoint refuses USDA_FDC, so insert directly): owned by a user, and flagged user_submission with no owner.
    $owned = rfSubmission(['source_id' => '900002', 'user_id' => $user->id], ['requires_review' => true, 'review_reasons' => ['user_submission']]);
    $flagged = rfSubmission(['source_id' => '900003'], ['requires_review' => true, 'review_reasons' => ['user_submission']]);
    $manual = rfSubmission(['source_provider' => 'MANUAL', 'source_id' => '900001', 'user_id' => $user->id]);

    $this->artisan('ai:seed-catalog')->expectsOutputToContain('approved 1')->assertSuccessful();
    (new AiMvpFoodSeeder)->run();

    expect($importer->fresh()->status)->toBe('approved')
        ->and($owned->fresh()->status)->toBe('pending')->and($flagged->fresh()->status)->toBe('pending')
        ->and($manual->fresh()->status)->toBe('pending')
        ->and(Food::count())->toBe(1)->and(FoodSubmission::query()->where('status', 'pending')->count())->toBe(3);
});

test('importer rows (no owner) are still approved', function () {
    $a = rfSubmission(['source_id' => '900001'], ['requires_review' => true, 'review_reasons' => ['variant_pick']]);
    $b = rfSubmission(['source_id' => '900002']);

    $this->artisan('ai:seed-catalog')->expectsOutputToContain('approved 2')->assertSuccessful();

    expect($a->fresh()->status)->toBe('approved')->and($b->fresh()->status)->toBe('approved')->and(Food::verified()->count())->toBe(2);
});
