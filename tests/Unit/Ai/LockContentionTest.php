<?php

use App\Exceptions\Ai\AiException;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;

// Real-shaped driver errors (no database needed). MySQL is recognised by errorInfo codes, SQLite by its message.

function lcPdo(string $message, ?array $errorInfo): PDOException
{
    $pdo = new PDOException($message);
    $pdo->errorInfo = $errorInfo;

    return $pdo;
}

function lcMysql(string $state, int $code, string $text): PDOException
{
    return lcPdo("SQLSTATE[$state]: General error: $code $text", [$state, $code, $text]);
}

/** The same driver error as a raw PDOException, as a QueryException, and nested as Laravel's DeadlockException. */
function lcShapes(PDOException $pdo): array
{
    $query = new QueryException('cc_a', 'select 1', [], $pdo);

    return [$pdo, $query, new DeadlockException($query->getMessage(), 0, $query), new RuntimeException('wrapped', 0, $query)];
}

it('recognises contention in every shape', function (PDOException $pdo) {
    foreach (lcShapes($pdo) as $shape) {
        expect(AiException::isLockContention($shape))->toBeTrue();
    }
})->with([
    'mysql lock wait timeout 1205' => fn () => lcMysql('HY000', 1205, 'Lock wait timeout exceeded; try restarting transaction'),
    'mysql deadlock 1213' => fn () => lcMysql('40001', 1213, 'Deadlock found when trying to get lock; try restarting transaction'),
    'any SQLSTATE 40001 serialization failure' => fn () => lcPdo('SQLSTATE[40001]: Serialization failure', ['40001', null, null]),
    'sqlite database is locked' => fn () => lcPdo('SQLSTATE[HY000]: General error: 5 database is locked', ['HY000', 5, 'database is locked']),
    'sqlite database table is locked' => fn () => lcPdo('SQLSTATE[HY000]: General error: 6 database table is locked', null),
]);

it('does not treat other database errors as contention', function (PDOException $pdo) {
    foreach (lcShapes($pdo) as $shape) {
        expect(AiException::isLockContention($shape))->toBeFalse();
    }
})->with([
    'mysql duplicate key 1062' => fn () => lcMysql('23000', 1062, "Duplicate entry 'x' for key 'k'"),
    'mysql missing table 1146' => fn () => lcMysql('42S02', 1146, "Table 'careplate_ai_cc.x' doesn't exist"),
    'mysql text without the driver code' => fn () => lcPdo('Lock wait timeout exceeded; try restarting transaction', ['HY000', 2006, 'MySQL server has gone away']),
    'sqlite disk I/O error' => fn () => lcPdo('SQLSTATE[HY000]: General error: 10 disk I/O error', ['HY000', 10, 'disk I/O error']),
]);

it('ignores contention-like text on an exception that is not a database error', function () {
    expect(AiException::isLockContention(new RuntimeException('database is locked')))->toBeFalse()
        ->and(AiException::isLockContention(new RuntimeException('Deadlock found')))->toBeFalse();
});
