<?php

use App\Models\ChatSession;
use App\Models\ChatTurn;
use App\Models\Food;
use App\Models\FoodRevision;
use App\Models\FoodSubmission;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function schemaFood(array $overrides = []): Food
{
    return Food::create(array_merge([
        'name' => 'Tempe goreng',
        'aliases' => ['tempe'],
        'source_provider' => 'tkpi',
        'source_id' => 'AP001',
        'snapshot_version' => '2020',
        'status' => 'verified',
        'current_revision' => 1,
    ], $overrides));
}

function schemaSession(User $user): ChatSession
{
    return ChatSession::create(['user_id' => $user->id, 'state' => 'active'])->fresh();
}

function schemaTurn(ChatSession $session, array $overrides = []): ChatTurn
{
    return ChatTurn::create(array_merge([
        'session_id' => $session->id,
        'client_turn_id' => (string) Str::uuid(),
        'sequence' => 1,
        'request_hash' => str_repeat('a', 64),
        'status' => 'pending',
        'token' => 'tok',
        'generation' => 1,
    ], $overrides));
}

test('all ai tables have exactly the planned columns', function () {
    $expected = [
        'foods' => [
            'id', 'name', 'aliases', 'basis', 'energy_kcal', 'protein_g', 'fat_g', 'carbohydrate_g',
            'sodium_mg', 'sugars_total_g', 'energy_method', 'source_provider', 'source_id', 'source_url',
            'snapshot_version', 'source_sha256', 'source_description', 'status', 'current_revision',
            'search_text', 'verifier_audit_id', 'verified_at', 'verified_by', 'created_at', 'updated_at',
        ],
        'food_revisions' => [
            'id', 'food_id', 'revision', 'action', 'status', 'snapshot', 'actor_id', 'actor_audit_id',
            'note', 'decision_hash', 'result', 'created_at',
        ],
        'food_submissions' => [
            'id', 'user_id', 'status', 'revision_counter', 'candidate', 'source_provider', 'source_id',
            'source_url', 'snapshot_version', 'source_sha256', 'food_id', 'decision_hash',
            'decision_result', 'decided_by', 'decided_at', 'created_at', 'updated_at',
        ],
        'chat_sessions' => [
            'id', 'user_id', 'title', 'state', 'generation', 'active_turn_id', 'active_token',
            'active_lease_expires_at', 'next_sequence', 'last_food_id', 'created_at', 'updated_at',
        ],
        'chat_turns' => [
            'id', 'session_id', 'client_turn_id', 'sequence', 'request_hash', 'status', 'token',
            'generation', 'lease_expires_at', 'message', 'food_id', 'amount_g', 'mode', 'response',
            'citations', 'food_refs', 'error_code', 'created_at', 'updated_at',
        ],
    ];

    foreach ($expected as $table => $columns) {
        expect(Schema::hasTable($table))->toBeTrue("missing table $table");
        $actual = Schema::getColumnListing($table);
        expect(array_values(array_diff($columns, $actual)))->toBe([], "$table missing columns")
            ->and(array_values(array_diff($actual, $columns)))->toBe([], "$table extra columns");
    }
});

test('sqlite connection keeps foreign keys on', function () {
    expect((int) DB::selectOne('pragma foreign_keys')->foreign_keys)->toBe(1);
})->skip(fn () => DB::getDriverName() !== 'sqlite', 'SQLite-specific: PRAGMA foreign_keys; the mysql twin below checks foreign_key_checks.');

test('mysql connection keeps foreign key checks on', function () {
    expect((int) DB::selectOne('select @@foreign_key_checks as v')->v)->toBe(1);
})->skip(fn () => DB::getDriverName() !== 'mysql', 'MySQL-specific: foreign_key_checks session variable.');

test('models use uuid primary keys', function () {
    $food = schemaFood();

    expect($food->id)->toBeString()->toHaveLength(36)
        ->and($food->fresh()->id)->toBe($food->id);
});

test('food defaults and nullable nutrients', function () {
    $food = schemaFood()->fresh();

    expect($food->basis)->toBe('100g')
        ->and($food->energy_kcal)->toBeNull()
        ->and($food->protein_g)->toBeNull()
        ->and($food->fat_g)->toBeNull()
        ->and($food->carbohydrate_g)->toBeNull()
        ->and($food->sodium_mg)->toBeNull()
        ->and($food->sugars_total_g)->toBeNull()
        ->and($food->aliases)->toBe(['tempe']);
});

test('food decimals are cast to strings and zero is preserved', function () {
    $food = schemaFood(['energy_kcal' => '201.5', 'sodium_mg' => '0'])->fresh();

    expect($food->energy_kcal)->toBeString()->toBe('201.5000')
        ->and($food->sodium_mg)->toBe('0.0000')
        ->and($food->protein_g)->toBeNull();
});

test('food source triple is unique', function () {
    schemaFood();

    expect(fn () => schemaFood(['name' => 'Duplikat']))->toThrow(UniqueConstraintViolationException::class);
    schemaFood(['source_id' => 'AP002']);
    schemaFood(['snapshot_version' => '2021']);
    expect(Food::count())->toBe(3);
});

test('scope verified excludes revoked foods', function () {
    schemaFood();
    schemaFood(['source_id' => 'AP002', 'status' => 'revoked']);

    expect(Food::verified()->pluck('source_id')->all())->toBe(['AP001']);
});

test('food revision pair is unique', function () {
    $food = schemaFood();
    $row = ['food_id' => $food->id, 'revision' => 1, 'action' => 'approve', 'status' => 'verified', 'snapshot' => ['a' => 1]];
    FoodRevision::create($row);

    expect(fn () => FoodRevision::create($row))->toThrow(UniqueConstraintViolationException::class);
    FoodRevision::create(array_merge($row, ['revision' => 2, 'action' => 'revoke']));
    expect($food->revisions()->count())->toBe(2)
        ->and(FoodRevision::first()->snapshot)->toBe(['a' => 1]);
});

test('food submission defaults and source triple is unique', function () {
    $row = [
        'status' => 'pending',
        'candidate' => ['name' => 'X', 'energy_kcal' => null],
        'source_provider' => 'tkpi',
        'source_id' => 'S1',
        'snapshot_version' => '2020',
    ];
    $submission = FoodSubmission::create($row)->fresh();

    expect($submission->status)->toBe('pending')
        ->and($submission->revision_counter)->toBe(1)
        ->and($submission->candidate)->toBe(['name' => 'X', 'energy_kcal' => null])
        ->and($submission->user_id)->toBeNull()
        ->and(fn () => FoodSubmission::create($row))->toThrow(UniqueConstraintViolationException::class);
});

test('chat session defaults', function () {
    $session = schemaSession(User::factory()->create());

    expect($session->generation)->toBe(1)
        ->and($session->next_sequence)->toBe(1)
        ->and($session->active_turn_id)->toBeNull()
        ->and($session->active_token)->toBeNull()
        ->and($session->active_lease_expires_at)->toBeNull();
});

test('chat turn client id and sequence are unique per session', function () {
    $user = User::factory()->create();
    $a = schemaSession($user);
    $b = schemaSession($user);
    $client = (string) Str::uuid();
    schemaTurn($a, ['client_turn_id' => $client, 'sequence' => 1]);

    expect(fn () => schemaTurn($a, ['client_turn_id' => $client, 'sequence' => 2]))
        ->toThrow(UniqueConstraintViolationException::class)
        ->and(fn () => schemaTurn($a, ['sequence' => 1]))
        ->toThrow(UniqueConstraintViolationException::class);

    schemaTurn($b, ['client_turn_id' => $client, 'sequence' => 1]);
    schemaTurn($a, ['sequence' => 2]);
    expect(ChatTurn::count())->toBe(3);
});

test('chat turn json and decimal casts', function () {
    $turn = schemaTurn(schemaSession(User::factory()->create()), [
        'amount_g' => '150',
        'response' => ['text' => 'ok'],
        'citations' => [['food_id' => 'x']],
        'food_refs' => [['id' => 'x', 'revision' => 2]],
    ])->fresh();

    expect($turn->amount_g)->toBe('150.00')
        ->and($turn->response)->toBe(['text' => 'ok'])
        ->and($turn->citations)->toBe([['food_id' => 'x']])
        ->and($turn->food_refs)->toBe([['id' => 'x', 'revision' => 2]])
        ->and($turn->session->id)->not->toBeNull();
});
