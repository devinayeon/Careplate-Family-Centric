<?php

declare(strict_types=1);

// Pure target guard shared by tools/ai-test-mysql.php and tests/TestCase.php. No I/O, no connections.
// Only the throwaway MySQL server at 127.0.0.1:3407 with a *_test / *_cc database may ever be reset by tests.

const AI_MYSQL_HOST = '127.0.0.1';
const AI_MYSQL_PORT = '3407';

/** Returns a refusal reason (never contains credentials), or null when the target is allowed. */
function aiMysqlTargetError(mixed $connection, mixed $host, mixed $port, mixed $database, mixed $socket = '', mixed $url = ''): ?string
{
    if ($connection !== 'mysql') {
        return 'Refused: the database connection must be mysql.';
    }
    if ($host !== AI_MYSQL_HOST || (string) $port !== AI_MYSQL_PORT) {
        return 'Refused: the target must be exactly '.AI_MYSQL_HOST.':'.AI_MYSQL_PORT.'.';
    }
    if (! is_string($database) || preg_match('/^[A-Za-z0-9_]+_(test|cc)$/', $database) !== 1) {
        return 'Refused: the database name must end in _test or _cc.';
    }
    if (($socket !== '' && $socket !== null) || ($url !== '' && $url !== null)) {
        return 'Refused: a socket or URL override would bypass the host and port check.';
    }

    return null;
}
