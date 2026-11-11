<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Two real Laravel/PDO connections (cc_a, cc_b) to ONE temp SQLite file under the system temp dir.
// Never the demo database and never :memory:. phpunit.xml's forced env is untouched.

const CC_BUSY_MS = 200;

/** Creates the temp file, registers both connections, migrates the schema and makes cc_a the default. */
function ccBoot(): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'careplate-cc-'.bin2hex(random_bytes(6)).'.sqlite';
    touch($path);
    foreach (['cc_a', 'cc_b'] as $name) {
        config(['database.connections.'.$name => array_merge(config('database.connections.sqlite'), [
            'database' => $path, 'url' => null, 'busy_timeout' => CC_BUSY_MS, 'transaction_mode' => 'IMMEDIATE',
        ])]);
        DB::purge($name);
    }
    DB::setDefaultConnection('cc_a');
    Artisan::call('migrate', ['--database' => 'cc_a', '--force' => true]);

    return $path;
}

/** Restores the memory default connection, closes both PDOs and removes the temp file. */
function ccShutdown(?string $path): void
{
    DB::setDefaultConnection('sqlite');
    foreach (['cc_a', 'cc_b'] as $name) {
        DB::purge($name);
    }
    gc_collect_cycles();
    if ($path !== null) {
        foreach ([$path, $path.'-journal', $path.'-wal', $path.'-shm'] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}

/** Runs $fn with $connection as the app default connection (what models/DB:: facade resolve), then restores. */
function ccOn(string $connection, Closure $fn): mixed
{
    $previous = DB::getDefaultConnection();
    DB::setDefaultConnection($connection);
    try {
        return $fn();
    } finally {
        DB::setDefaultConnection($previous);
    }
}

/** The connection's own PDO handle id, proves A and B are distinct handles. */
function ccPdoId(string $connection): int
{
    return spl_object_id(DB::connection($connection)->getPdo());
}
