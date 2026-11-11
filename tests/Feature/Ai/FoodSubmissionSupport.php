<?php

use App\Models\FoodSubmission;
use App\Models\User;
use App\Services\Ai\Foods\SnapshotManifest;
use Database\Seeders\AiPermissionSeeder;
use Illuminate\Testing\TestResponse;

// SYNTHETIC users, foods and sources only. Shared by the submission and admin review HTTP tests.

require_once __DIR__.'/ReviewFoodSupport.php';
require_once __DIR__.'/ChatApiSupport.php';

/** Like rfManifest() but lists the ids under provider MANUAL: the only way a user-submitted row can pass approval. */
function fsManifest(array $ids = ['900001', '900002']): void
{
    $path = sys_get_temp_dir().'/fs-manifest-'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($path, json_encode([
        'schema_version' => SnapshotManifest::SCHEMA,
        'snapshots' => [[
            'source_provider' => 'MANUAL', 'snapshot_version' => '2099-12-31', 'data_type' => 'Foundation',
            'url' => 'https://example.test/x.zip', 'sha256' => RF_SHA, 'retrieved_at' => '2099-12-31T00:00:00+00:00',
            'record_count' => count($ids), 'raw_json_file' => 'x.json', 'imported_source_ids' => $ids,
        ]],
    ]));
    app()->instance(SnapshotManifest::class, new SnapshotManifest($path));
}

function fsUser(string $role = 'family_head'): User
{
    test()->seed(AiPermissionSeeder::class);

    return ctUser($role);
}

/** A valid body (contracts FoodCandidate) whose source triple exists in the bound test manifest. */
function fsBody(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Tahu contoh',
        'aliases' => ['tahu contoh', 'tahu putih'],
        'basis' => '100g',
        'nutrients' => [
            'energy_kcal' => 80, 'fat_g' => 4.8, 'sugars_total_g' => null,
            'protein_g' => 10, 'carbohydrate_g' => 1.9, 'sodium_mg' => 7,
        ],
        'source' => [
            'provider' => 'MANUAL', 'source_id' => '900001', 'source_url' => 'https://example.test/food/900001',
            'snapshot_version' => '2099-12-31', 'source_sha256' => RF_SHA,
        ],
    ], $overrides);
}

/** A guest request must not inherit an earlier actingAs() of the same test. */
function fsActor(?User $user): mixed
{
    if ($user === null) {
        app('auth')->forgetGuards();

        return test();
    }

    return test()->actingAs($user);
}

function fsSubmit(?User $user, array $body): TestResponse
{
    $test = fsActor($user);

    return $test->postJson('/ai/v1/food-submissions', $body);
}

/** Submits as a family head and returns the submission model (asserts 201). */
function fsPending(array $overrides = []): FoodSubmission
{
    $response = fsSubmit(fsUser(), fsBody($overrides))->assertCreated();

    return FoodSubmission::query()->findOrFail($response->json('submission_id'));
}

function fsApprove(?User $user, string $id, array $body): TestResponse
{
    $test = fsActor($user);

    return $test->postJson("/ai/v1/admin/food-submissions/{$id}/approve", $body);
}

function fsRevoke(?User $user, string $id, array $body): TestResponse
{
    $test = fsActor($user);

    return $test->postJson("/ai/v1/admin/foods/{$id}/revoke", $body);
}

function fsApproveBody(int $revision = 1, bool $ack = true): array
{
    return ['expected_revision' => $revision, 'reviewer_note' => 'Sumber dan nilai dicocokkan.', 'acknowledge_review_flags' => $ack];
}
