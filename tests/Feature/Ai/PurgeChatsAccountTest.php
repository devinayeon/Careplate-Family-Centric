<?php

use App\Models\ChatSession;
use App\Models\ChatTurn;
use App\Models\Food;
use App\Models\FoodRevision;
use App\Models\FoodSubmission;
use App\Models\User;
use Database\Seeders\AiPermissionSeeder;
use Illuminate\Support\Facades\DB;

// SYNTHETIC users, sessions and turns only. Account deletion versus chat content and the catalog (Task 13).

require_once __DIR__.'/ChatTurnSupport.php';

beforeEach(function () {
    test()->seed(AiPermissionSeeder::class);
});

/** @return array{0: Food, 1: FoodRevision, 2: FoodSubmission} */
function pgCatalog(User $user): array
{
    $food = Food::create([
        'name' => 'Tahu sintetis', 'source_provider' => 'tkpi', 'source_id' => 'PG1', 'snapshot_version' => '2020',
        'status' => 'verified', 'current_revision' => 1, 'verified_by' => $user->id, 'verified_at' => now(),
    ]);
    $revision = FoodRevision::create([
        'food_id' => $food->id, 'revision' => 1, 'action' => 'approve', 'status' => 'verified',
        'snapshot' => ['name' => 'Tahu sintetis'], 'actor_id' => $user->id,
    ]);
    $submission = FoodSubmission::create([
        'user_id' => $user->id, 'status' => 'approved', 'candidate' => ['name' => 'Tahu sintetis'],
        'source_provider' => 'tkpi', 'source_id' => 'PG1', 'snapshot_version' => '2020', 'food_id' => $food->id,
        'decided_by' => $user->id, 'decided_at' => now(),
    ]);

    return [$food, $revision, $submission];
}

function pgAssertAccountGone(User $user, array $catalog, array $mine, ChatSession $other): void
{
    [$food, $revision, $submission] = $catalog;
    expect(User::find($user->id))->toBeNull()
        ->and(ChatSession::where('user_id', $user->id)->count())->toBe(0)
        ->and(ChatTurn::whereIn('session_id', $mine)->count())->toBe(0)
        ->and(DB::table('chat_sessions')->where('id', $other->id)->exists())->toBeTrue()->and(ChatTurn::where('session_id', $other->id)->count())->toBe(1)
        ->and($food->fresh()->verified_by)->toBeNull()->and($food->fresh()->status)->toBe('verified')
        ->and($revision->fresh()->actor_id)->toBeNull()->and($revision->fresh()->snapshot)->toBe(['name' => 'Tahu sintetis'])
        ->and($submission->fresh()->user_id)->toBeNull()->and($submission->fresh()->decided_by)->toBeNull();
}

test('deleting a user via the model cascades chat content while catalog rows stay with null user FKs', function () {
    $user = ctUser();
    $catalog = pgCatalog($user);
    $a = ctSession($user);
    $b = ctSession($user, ['state' => 'deleted', 'generation' => 2]);
    ctTurn($a, 1);
    ctTurn($b, 1);
    $other = ctSession();
    ctTurn($other, 1);

    $user->delete();

    pgAssertAccountGone($user, $catalog, [$a->id, $b->id], $other);
});

test('DELETE /profile still hard-deletes the account with the new foreign keys', function () {
    $user = ctUser();
    $catalog = pgCatalog($user);
    $a = ctSession($user);
    ctTurn($a, 1);
    $other = ctSession();
    ctTurn($other, 1);

    test()->actingAs($user)->delete('/profile', ['password' => 'password'])
        ->assertSessionHasNoErrors()->assertRedirect('/');

    test()->assertGuest();
    pgAssertAccountGone($user, $catalog, [$a->id], $other);
});
