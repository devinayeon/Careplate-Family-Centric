<?php

use App\Actions\Foods\ReviewFood;
use App\Exceptions\Ai\AiException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require_once __DIR__.'/ReviewFoodSupport.php';
require_once __DIR__.'/ChatFoodSupport.php';

beforeEach(fn () => rfManifest());

// Rollback of partial writes needs a real outermost transaction (Laravel skips the savepoint rollback of a lock error
// nested in the test transaction), so it is asserted on two real connections in Concurrency/TwoConnectionSqliteTest.

/** Makes the first statement that touches $table fail the way SQLite reports contention. */
function rfFailOnInsert(string $table, Throwable $error): void
{
    DB::listen(function ($query) use ($table, $error) {
        if (str_starts_with($query->sql, 'insert into "'.$table.'"')) {
            throw $error;
        }
    });
}

it('maps a SQLite "database is locked" PDOException during approve to a retryable DB_BUSY', function () {
    $submission = rfSubmission();
    rfFailOnInsert('food_revisions', new PDOException('SQLSTATE[HY000]: General error: 5 database is locked'));

    $error = rfThrown(fn () => app(ReviewFood::class)->approve($submission->id, null, 'script:test', 2, 'Sumber dicocokkan.'));

    expect($error)->toBeInstanceOf(AiException::class)
        ->and($error->errorCode)->toBe('DB_BUSY')
        ->and($error->status)->toBe(503)
        ->and($error->retryable)->toBeTrue();
});

it('maps "database table is locked" during revoke to DB_BUSY', function () {
    $food = cfFood(['current_revision' => 3]);
    rfFailOnInsert('food_revisions', new PDOException('SQLSTATE[HY000]: General error: 6 database table is locked'));

    $error = rfThrown(fn () => app(ReviewFood::class)->revoke($food->id, null, 'script:test', 3, 'Alasan.'));

    expect($error->errorCode)->toBe('DB_BUSY')->and($error->retryable)->toBeTrue();
});

it('does NOT remap a non-lock QueryException from approve or revoke', function () {
    $submission = rfSubmission();
    $food = cfFood(['current_revision' => 3]);
    Schema::drop('food_revisions');

    $approve = fn () => app(ReviewFood::class)->approve($submission->id, null, 'script:test', 2, 'Sumber dicocokkan.');
    $revoke = fn () => app(ReviewFood::class)->revoke($food->id, null, 'script:test', 3, 'Alasan.');

    expect($approve)->toThrow(QueryException::class, 'no such table')
        ->and($revoke)->toThrow(QueryException::class, 'no such table');
});

it('does NOT remap an unrelated PDOException', function () {
    $submission = rfSubmission();
    rfFailOnInsert('food_revisions', new PDOException('SQLSTATE[HY000]: General error: 11 database disk image is malformed'));

    expect(fn () => app(ReviewFood::class)->approve($submission->id, null, 'script:test', 2, 'Sumber dicocokkan.'))
        ->toThrow(PDOException::class, 'malformed');
});
