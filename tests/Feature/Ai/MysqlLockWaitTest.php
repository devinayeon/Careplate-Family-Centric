<?php

use Illuminate\Support\Facades\DB;
use Pdo\Mysql;

// MySQL's default lock wait is 50 s, far past the 20 s chat deadline: the app connection waits at most 5 s
// (like SQLite's busy_timeout) and then fails as a retryable SESSION_BUSY / DB_BUSY.

test('the mysql connection config sets a 5 s lock wait on connect', function () {
    expect(config('database.connections.mysql.options')[Mysql::ATTR_INIT_COMMAND] ?? null)
        ->toBe('SET SESSION innodb_lock_wait_timeout = 5');
});

test('the app mysql connection reports a 5 s lock wait after connect', function () {
    expect((int) DB::selectOne('select @@session.innodb_lock_wait_timeout as t')->t)->toBe(5);
})->skip(fn () => DB::getDriverName() !== 'mysql', 'MySQL only: the session variable does not exist on SQLite.');
