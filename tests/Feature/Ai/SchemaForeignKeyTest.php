<?php

use App\Models\ChatSession;
use App\Models\ChatTurn;
use App\Models\Food;
use App\Models\FoodRevision;
use App\Models\FoodSubmission;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

function fkCatalog(User $user): array
{
    $food = Food::create([
        'name' => 'Nasi putih',
        'source_provider' => 'tkpi',
        'source_id' => 'AP100',
        'snapshot_version' => '2020',
        'status' => 'verified',
        'current_revision' => 1,
        'verified_by' => $user->id,
        'verified_at' => now(),
    ]);
    $revision = FoodRevision::create([
        'food_id' => $food->id,
        'revision' => 1,
        'action' => 'approve',
        'status' => 'verified',
        'snapshot' => ['name' => 'Nasi putih'],
        'actor_id' => $user->id,
    ]);
    $submission = FoodSubmission::create([
        'user_id' => $user->id,
        'status' => 'approved',
        'candidate' => ['name' => 'Nasi putih'],
        'source_provider' => 'tkpi',
        'source_id' => 'AP100',
        'snapshot_version' => '2020',
        'food_id' => $food->id,
        'decided_by' => $user->id,
        'decided_at' => now(),
    ]);

    return [$food, $revision, $submission];
}

test('deleting a user keeps catalog rows and nulls user references', function () {
    $user = User::factory()->create();
    [$food, $revision, $submission] = fkCatalog($user);

    $user->delete();

    expect($food->fresh()->verified_by)->toBeNull()
        ->and($food->fresh()->status)->toBe('verified')
        ->and($revision->fresh()->actor_id)->toBeNull()
        ->and($revision->fresh()->snapshot)->toBe(['name' => 'Nasi putih'])
        ->and($submission->fresh()->user_id)->toBeNull()
        ->and($submission->fresh()->decided_by)->toBeNull()
        ->and($submission->fresh()->status)->toBe('approved');
});

test('deleting a user cascades chat sessions and turns', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $session = ChatSession::create(['user_id' => $user->id, 'state' => 'active']);
    $kept = ChatSession::create(['user_id' => $other->id, 'state' => 'active']);
    foreach ([$session, $kept] as $s) {
        ChatTurn::create([
            'session_id' => $s->id,
            'client_turn_id' => (string) Str::uuid(),
            'sequence' => 1,
            'request_hash' => str_repeat('b', 64),
            'status' => 'completed',
            'token' => 't',
            'generation' => 1,
        ]);
    }

    $user->delete();

    expect(ChatSession::count())->toBe(1)
        ->and(ChatSession::first()->id)->toBe($kept->id)
        ->and(ChatTurn::count())->toBe(1)
        ->and(ChatTurn::first()->session_id)->toBe($kept->id);
});

test('deleting a food cascades revisions and nulls submission food', function () {
    [$food, , $submission] = fkCatalog(User::factory()->create());

    $food->delete();

    expect(FoodRevision::count())->toBe(0)
        ->and($submission->fresh()->food_id)->toBeNull();
});

test('account deletion via profile endpoint keeps catalog and removes chats', function () {
    $user = User::factory()->create();
    [$food] = fkCatalog($user);
    ChatSession::create(['user_id' => $user->id, 'state' => 'active']);

    $this->actingAs($user)
        ->delete('/profile', ['password' => 'password'])
        ->assertSessionHasNoErrors();

    expect(User::count())->toBe(0)
        ->and(ChatSession::count())->toBe(0)
        ->and($food->fresh()->verified_by)->toBeNull();
});

test('foreign keys reject orphan rows', function () {
    expect(fn () => ChatSession::create(['user_id' => 9999, 'state' => 'active']))
        ->toThrow(QueryException::class);
});
