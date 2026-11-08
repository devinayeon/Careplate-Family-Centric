<?php

declare(strict_types=1);
use PHPUnit\TextUI\Configuration\PhpHandler;
use PHPUnit\TextUI\XmlConfiguration\Loader;

// Runs the Pest suite on the throwaway MySQL server only (127.0.0.1:3407, *_test / *_cc databases).
// Usage: php tools/ai-test-mysql.php [--filter=x] [--compact] [tests/Feature/Ai/SomeTest.php] ...
// Host environment can not re-point it: DB_*/APP_* are scrubbed and phpunit.mysql.xml force-sets the target.
if (PHP_SAPI !== 'cli') {
    exit(2);
}
require_once __DIR__.'/ai-test-mysql-guard.php';

function mysqlFail(string $message): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}

function mysqlWithin(string $target, string $directory): bool
{
    $norm = fn (string $p): string => rtrim(strtolower(str_replace('\\', '/', $p)), '/');

    return $norm($target) === $norm($directory) || str_starts_with($norm($target), $norm($directory).'/');
}

/** Same allowlist as tools/ai-dev.php test: selection flags and paths inside tests/ only. */
function mysqlTestArguments(array $arguments, string $root): array
{
    $takesValue = ['--filter', '--testsuite', '--group', '--exclude-group'];
    $switches = ['--compact', '--stop-on-failure', '--stop-on-error', '--bail', '--no-coverage'];
    for ($index = 0; $index < count($arguments); $index++) {
        $argument = $arguments[$index];
        $option = explode('=', $argument, 2)[0];
        if (in_array($option, $takesValue, true)) {
            if (! str_contains($argument, '=')) {
                $index++;
                if (! isset($arguments[$index]) || str_starts_with($arguments[$index], '-')) {
                    mysqlFail('A test selection option is missing its value.');
                }
            }

            continue;
        }
        if (in_array($argument, $switches, true)) {
            continue;
        }
        if (str_starts_with($argument, '-') || preg_match('#^([A-Za-z]:)?[/\\\\]#', $argument) === 1) {
            mysqlFail('Unsupported test option. Only test selection flags and paths under tests/ are accepted.');
        }
        $target = realpath($root.DIRECTORY_SEPARATOR.$argument);
        if ($target === false || ! mysqlWithin($target, $root.'/tests')) {
            mysqlFail('A test path must resolve inside this worktree tests/ directory.');
        }
    }

    return $arguments;
}

$root = realpath(dirname(__DIR__));
if ($root === false || ! is_file($root.'/vendor/autoload.php') || ! is_file($root.'/phpunit.mysql.xml')) {
    mysqlFail('Backend dependencies or phpunit.mysql.xml are missing.');
}
chdir($root);
require $root.'/vendor/autoload.php';
$arguments = mysqlTestArguments(array_slice($argv, 1), $root);

// Child environment: keep OS/PATH, drop every inherited application setting (same pattern as tools/ai-dev.php).
$pattern = '/^(APP_|DB_|DATABASE_|MYSQL_|AI_|NUTRITION_|GEMINI_|GOOGLE_API_KEY$|CACHE_|SESSION_|QUEUE_|MAIL_|LOG_|VIEW_|LARAVEL_|BROADCAST_|FILESYSTEM_|REDIS_|MEMCACHED_|AWS_|PUSHER_)/i';
$inherited = getenv();
$child = [];
foreach (is_array($inherited) ? $inherited : [] as $name => $value) {
    if (is_string($name) && preg_match($pattern, $name) !== 1) {
        $child[$name] = (string) $value;
    }
}

// Resolve what phpunit.mysql.xml really forces, starting from hostile values that only force="true" can beat.
foreach (['DB_CONNECTION' => 'sqlite', 'DB_HOST' => 'evil.example', 'DB_PORT' => '3306', 'DB_DATABASE' => 'production',
    'DB_SOCKET' => '/tmp/evil.sock', 'DB_URL' => 'mysql://evil.example/production'] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $value;
}
try {
    (new PhpHandler)->handle((new Loader)->load($root.'/phpunit.mysql.xml')->php());
} catch (Throwable) {
    mysqlFail('phpunit.mysql.xml could not be loaded. No tests were run.');
}
$refusal = aiMysqlTargetError(getenv('DB_CONNECTION'), getenv('DB_HOST'), getenv('DB_PORT'), getenv('DB_DATABASE'),
    getenv('DB_SOCKET'), getenv('DB_URL'));
if ($refusal !== null) {
    mysqlFail($refusal.' No tests were run.');
}

$command = [PHP_BINARY, $root.'/vendor/bin/pest', '--configuration', 'phpunit.mysql.xml', ...$arguments];
$process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $root, $child, ['bypass_shell' => true]);
if (! is_resource($process)) {
    mysqlFail('Could not start Pest.');
}
exit(proc_close($process));
