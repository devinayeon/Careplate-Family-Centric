<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/TwoConnectionSupport.php';
require_once __DIR__.'/../../../../tools/ai-test-mysql-guard.php';

// Two real Laravel/PDO connections (cc_a, cc_b) to the throwaway MySQL database careplate_ai_cc on 127.0.0.1:3407.
// The target is hard-coded here (never read from env) and re-checked by the shared guard before any connection.
// Each connection waits at most 1 s for a row lock (innodb_lock_wait_timeout), so a blocked statement fails fast.

const CM_DATABASE = 'careplate_ai_cc';

/** The connection config for cc_a/cc_b: the app's mysql defaults with the target pinned. */
function cmConfig(): array
{
    return array_merge(config('database.connections.mysql'), [
        'driver' => 'mysql', 'host' => AI_MYSQL_HOST, 'port' => AI_MYSQL_PORT, 'database' => CM_DATABASE,
        'username' => 'root', 'password' => '', 'unix_socket' => '', 'url' => null,
        'options' => [PDO::ATTR_TIMEOUT => 2],
    ]);
}

/**
 * Registers both connections (refusing any target but 127.0.0.1:3407/*_cc), returns the skip reason when the
 * server is unreachable, migrates once per process, empties every table, and makes cc_a the default.
 */
function cmBoot(): ?string
{
    foreach (['cc_a', 'cc_b'] as $name) {
        config(['database.connections.'.$name => cmConfig()]);
        $c = config('database.connections.'.$name);
        $refusal = aiMysqlTargetError($c['driver'], $c['host'], $c['port'], $c['database'], $c['unix_socket'], $c['url']);
        if ($refusal !== null) {
            throw new RuntimeException($refusal);
        }
        DB::purge($name);
    }
    if (! extension_loaded('pdo_mysql')) {
        return 'pdo_mysql is not loaded.';
    }
    try {
        DB::connection('cc_a')->getPdo();
    } catch (Throwable) {
        return 'MySQL test server 127.0.0.1:3407 ('.CM_DATABASE.') is unreachable.';
    }

    DB::setDefaultConnection('cc_a');
    static $migrated = false;
    if (! $migrated) {
        Artisan::call('migrate:fresh', ['--database' => 'cc_a', '--force' => true]);
        $migrated = true;
    }
    cmTruncate();
    foreach (['cc_a', 'cc_b'] as $name) {
        DB::connection($name)->statement('SET SESSION innodb_lock_wait_timeout = 1');
    }

    return null;
}

/** Deletes every row except the migration log (FK checks off on this one session only). */
function cmTruncate(): void
{
    $db = DB::connection('cc_a');
    $db->statement('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($db->select('SHOW TABLES') as $row) {
        $table = array_values((array) $row)[0];
        if ($table !== 'migrations') {
            $db->table($table)->delete();
        }
    }
    $db->statement('SET FOREIGN_KEY_CHECKS = 1');
}

/** Restores the suite's default connection and closes both PDOs (an open transaction is rolled back by MySQL). */
function cmShutdown(string $default): void
{
    DB::setDefaultConnection($default);
    foreach (['cc_a', 'cc_b'] as $name) {
        DB::purge($name);
    }
}

/** Runs $competing once, right after $connection executed a statement starting with $sqlPrefix (inside its transaction). */
function cmAfter(string $connection, string $sqlPrefix, Closure $competing): object
{
    $probe = (object) ['armed' => true, 'outcome' => null, 'ran' => false];
    DB::listen(function ($query) use ($probe, $connection, $sqlPrefix, $competing) {
        if ($probe->armed && $query->connectionName === $connection && str_starts_with($query->sql, $sqlPrefix)) {
            $probe->armed = false;
            $probe->ran = true;
            try {
                $probe->outcome = $competing();
            } catch (Throwable $e) {
                $probe->outcome = $e;
            }
        }
    });

    return $probe;
}

/** Returns the Throwable $fn throws, or null. */
function cmCaught(Closure $fn): ?Throwable
{
    try {
        $fn();
    } catch (Throwable $e) {
        return $e;
    }

    return null;
}
