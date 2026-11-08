<?php

use Database\Seeders\AiPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/FoodSubmissionSupport.php';

beforeEach(function () {
    fsManifest();
    Http::fake();
    test()->seed(AiPermissionSeeder::class);
});

/** Makes the first insert into $table fail the way SQLite reports contention (or any other database error). */
function dbFailOnInsert(string $table, Throwable $error): void
{
    DB::listen(function ($query) use ($table, $error) {
        if (str_starts_with($query->sql, 'insert into "'.$table.'"')) {
            throw $error;
        }
    });
}

function dbLockError(bool $asQuery): Throwable
{
    $pdo = new PDOException('SQLSTATE[HY000]: General error: 5 database is locked');

    return $asQuery ? new QueryException('sqlite', 'insert', [], $pdo) : $pdo;
}

it('maps SQLite lock contention while creating a submission to a retryable DB_BUSY', function (bool $asQuery) {
    dbFailOnInsert('food_submissions', dbLockError($asQuery));

    chatError(fsSubmit(fsUser(), fsBody()), 503, 'DB_BUSY', true);
})->with([[true], [false]]);

it('maps SQLite lock contention while creating a chat session to a retryable DB_BUSY', function (bool $asQuery) {
    dbFailOnInsert('chat_sessions', dbLockError($asQuery));

    chatError(test()->actingAs(ctUser())->postJson('/ai/v1/chat/sessions', []), 503, 'DB_BUSY', true);
})->with([[true], [false]]);

it('does not remap a non-lock database error from either create', function () {
    dbFailOnInsert('food_submissions', new QueryException('sqlite', 'insert', [], new PDOException('disk I/O error')));
    dbFailOnInsert('chat_sessions', new QueryException('sqlite', 'insert', [], new PDOException('disk I/O error')));

    chatError(fsSubmit(fsUser(), fsBody()), 500, 'INTERNAL');
    chatError(test()->actingAs(ctUser())->postJson('/ai/v1/chat/sessions', []), 500, 'INTERNAL');
});
