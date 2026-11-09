<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Helpers that let the same assertions run on SQLite (memory) and on the throwaway MySQL server.

/** True when $sql is an "insert into <table>" statement, whatever the driver's identifier quoting (" or `). */
function dbIsInsertInto(string $sql, string $table): bool
{
    return preg_match('/^insert into [`"]'.preg_quote($table, '/').'[`"]/i', $sql) === 1;
}

/**
 * Makes every later statement against $table fail with a "table is gone" QueryException and returns the message
 * fragment that error carries. SQLite really drops the table (the test transaction rolls the DDL back). On MySQL a
 * DROP TABLE commits implicitly, is refused by foreign keys and would leak the change into later tests, so the
 * driver's own missing-table error is raised from the first statement that touches the table instead.
 */
function dbBreakTable(string $table): string
{
    if (DB::getDriverName() === 'sqlite') {
        Schema::drop($table);

        return 'no such table';
    }
    $database = DB::connection()->getDatabaseName();
    $pdo = new PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table '{$database}.{$table}' doesn't exist");
    DB::listen(function ($query) use ($table, $pdo) {
        if (preg_match('/[`"]'.preg_quote($table, '/').'[`"]/', $query->sql) === 1) {
            throw new QueryException($query->connectionName, $query->sql, $query->bindings, $pdo);
        }
    });

    return "doesn't exist";
}

/**
 * Recursively sorts associative keys on MySQL only: its JSON columns return object keys in their own order and JSON
 * objects are unordered. On SQLite this is a no-op, so the exact key order stays asserted there.
 */
function dbCanonical(mixed $value): mixed
{
    if (DB::getDriverName() === 'sqlite' || ! is_array($value)) {
        return $value;
    }
    $value = array_map('dbCanonical', $value);
    if (! array_is_list($value)) {
        ksort($value);
    }

    return $value;
}
