<?php

declare(strict_types=1);
use Database\Seeders\AiPermissionSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Env;

// Local linked-worktree commands only. This file never prints environment secrets.
if (PHP_SAPI !== 'cli') {
    exit(2);
}

function aiFail(string $message): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}

function aiAbsolute(string $path): bool
{
    return str_starts_with($path, '/') || str_starts_with($path, '\\')
        || (strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':'
            && ($path[2] === '/' || $path[2] === '\\'));
}

function aiPath(string $path): string
{
    $resolved = realpath($path);
    $value = str_replace('\\', '/', $resolved !== false ? $resolved : $path);

    return PHP_OS_FAMILY === 'Windows' ? strtolower(rtrim($value, '/')) : rtrim($value, '/');
}

function aiInside(string $path, string $root): bool
{
    if (! aiAbsolute($path)) {
        $path = $root.DIRECTORY_SEPARATOR.$path;
    }
    $normalized = str_replace('\\', '/', $path);
    if (in_array('..', explode('/', $normalized), true)) {
        return false;
    }
    // Resolve the nearest existing ancestor too, so a symlink cannot escape the worktree.
    $ancestor = $path;
    while (! file_exists($ancestor) && dirname($ancestor) !== $ancestor) {
        $ancestor = dirname($ancestor);
    }
    $base = aiPath($root);
    $candidate = aiPath($ancestor);

    return $candidate === $base || str_starts_with($candidate, $base.'/');
}

function aiSetEnv(string $name, string $value): void
{
    putenv($name.'='.$value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

function aiRun(array $command, string $root): int
{
    $environment = getenv();
    $environment = is_array($environment) ? $environment : [];
    foreach ($_ENV as $name => $value) {
        if (is_string($name) && is_scalar($value)) {
            $environment[$name] = (string) $value;
        }
    }
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $root, $environment, ['bypass_shell' => true]);
    if (! is_resource($process)) {
        aiFail('Could not start the selected PHP command.');
    }

    return proc_close($process);
}

function aiTestArguments(array $arguments, string $root): array
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
                    aiFail('A test selection option is missing its value.');
                }
            }

            continue;
        }
        if (in_array($argument, $switches, true)) {
            continue;
        }
        if (str_starts_with($argument, '-') || aiAbsolute($argument)) {
            aiFail('Unsupported test option. Only test selection flags and paths under tests/ are accepted.');
        }
        $target = realpath($root.DIRECTORY_SEPARATOR.$argument);
        $testDirectory = aiPath($root.'/tests');
        if ($target === false || ! aiInside($target, $root)
            || (aiPath($target) !== $testDirectory && ! str_starts_with(aiPath($target), $testDirectory.'/'))) {
            aiFail('A test path must resolve inside this worktree tests/ directory.');
        }
    }

    return $arguments;
}

$command = $argv[1] ?? 'help';
$arguments = array_slice($argv, 2);
$menu = ['doctor', 'migrate', 'seed', 'test', 'lint', 'analyse', 'serve', 'ai'];
if (in_array($command, ['help', '--help', '-h'], true)) {
    fwrite(STDOUT, "Usage: php tools/ai-dev.php doctor [--testing]|migrate|seed|test [selection]|lint|analyse|serve|ai <ai:name> [args]\n");
    exit(0);
}
if (! in_array($command, $menu, true)) {
    aiFail('Unknown command. Run php tools/ai-dev.php help.');
}
$testing = $command === 'test' || ($command === 'doctor' && $arguments === ['--testing']);
if (! in_array($command, ['test', 'ai'], true) && ! ($command === 'doctor' && $arguments === ['--testing']) && $arguments !== []) {
    aiFail('This command does not accept extra arguments.');
}
if ($command === 'ai') {
    // Only project ai:* artisan commands; options that could re-point the environment are refused.
    if (! isset($arguments[0]) || preg_match('/^ai:[A-Za-z0-9:_-]+$/', $arguments[0]) !== 1) {
        aiFail('The ai command only runs artisan commands named ai:<name>.');
    }
    foreach (array_slice($arguments, 1) as $argument) {
        if (preg_match('/^--(env|database)/', $argument) === 1) {
            aiFail('The --env and --database options are not accepted.');
        }
    }
}

try {
    $root = realpath(dirname(__DIR__));
    if ($root === false || ! is_file($root.'/.git') || is_dir($root.'/.git')) {
        aiFail('Use the linked AI worktree, not the main checkout.');
    }
    $gitPointer = trim((string) file_get_contents($root.'/.git'));
    if (! str_starts_with($gitPointer, 'gitdir: ')) {
        aiFail('The linked-worktree Git pointer is invalid.');
    }
    $gitLocation = substr($gitPointer, 8);
    $gitDirectory = realpath(aiAbsolute($gitLocation) ? $gitLocation : $root.'/'.$gitLocation);
    if ($gitDirectory === false || ! is_file($gitDirectory.'/commondir') || ! is_file($gitDirectory.'/gitdir')) {
        aiFail('Linked-worktree metadata was not found.');
    }
    if (aiPath(trim((string) file_get_contents($gitDirectory.'/gitdir'))) !== aiPath($root.'/.git')) {
        aiFail('Git metadata belongs to a different worktree.');
    }
    if (trim((string) file_get_contents($gitDirectory.'/HEAD')) !== 'ref: refs/heads/feat/ai-mvp-4days') {
        aiFail('This helper requires the feat/ai-mvp-4days branch.');
    }
    chdir($root);
    if (! is_file($root.'/vendor/autoload.php')) {
        aiFail('Backend dependencies are missing. Install from the existing lockfile first.');
    }
    if ($command === 'test') {
        $arguments = aiTestArguments($arguments, $root);
    }

    // Keep OS/PATH settings; remove inherited application configuration from all adapters.
    $inherited = getenv();
    $inherited = is_array($inherited) ? $inherited : [];
    $names = array_unique(array_merge(array_keys($inherited), array_keys($_ENV), array_keys($_SERVER)));
    foreach ($names as $name) {
        if (is_string($name) && preg_match('/^(APP_|DB_|DATABASE_|AI_|NUTRITION_|GEMINI_|GOOGLE_API_KEY$|CACHE_|SESSION_|QUEUE_|MAIL_|LOG_|VIEW_|LARAVEL_|BROADCAST_|FILESYSTEM_|REDIS_|MEMCACHED_|AWS_|PUSHER_)/i', $name)) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }
    $suffix = $testing ? '-testing' : '';
    $cacheNames = [
        'APP_CONFIG_CACHE' => 'config',
        'APP_SERVICES_CACHE' => 'services',
        'APP_PACKAGES_CACHE' => 'packages',
        'APP_ROUTES_CACHE' => 'routes-v7',
        'APP_EVENTS_CACHE' => 'events',
    ];
    $database = $testing ? ':memory:' : $root.'/database/careplate-ai-demo.sqlite';
    $forced = [
        'APP_ENV' => $testing ? 'testing' : 'local',
        'APP_URL' => 'http://127.0.0.1:8001',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $database,
        'DB_URL' => '',
        'LARAVEL_STORAGE_PATH' => $root.'/storage',
        'VIEW_COMPILED_PATH' => $root.'/storage/framework/views',
        'SESSION_COOKIE' => 'careplate_ai_mvp_session',
        'SESSION_DOMAIN' => 'null',
        'SESSION_SECURE_COOKIE' => 'false',
        'SESSION_SAME_SITE' => 'lax',
        'LOG_CHANNEL' => $testing ? 'null' : 'single',
        'LOG_STACK' => 'single',
        'LOG_DEPRECATIONS_CHANNEL' => 'null',
        'MAIL_MAILER' => 'array',
        'BROADCAST_CONNECTION' => 'null',
        'QUEUE_CONNECTION' => 'sync',
    ];
    foreach ($cacheNames as $variable => $file) {
        // Laravel's Windows cache-path resolver expects these to be relative.
        $forced[$variable] = 'bootstrap/cache/'.$file.$suffix.'.php';
    }
    if ($testing) {
        $forced += [
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'AI_ENABLED' => 'false',
            'AI_GEMINI_KEY' => '',
            'GEMINI_API_KEY' => '',
            'GOOGLE_API_KEY' => '',
        ];
    }
    foreach ($forced as $name => $value) {
        aiSetEnv($name, $value);
    }

    require $root.'/vendor/autoload.php';
    $environmentFile = $testing ? '.env.testing' : '.env';
    if (! is_file($root.'/'.$environmentFile)) {
        aiFail('The selected local/testing environment file is missing.');
    }
    try {
        Dotenv\Dotenv::createImmutable($root, $environmentFile)->load();
    } catch (Throwable) {
        aiFail('The selected environment file could not be parsed. Inspect it privately; no contents were printed.');
    }
    foreach ($forced as $name => $value) {
        aiSetEnv($name, $value);
    }
    if (! $testing && (! is_file($database) || ! aiInside($database, $root))) {
        aiFail('The dedicated worktree demo SQLite file is missing or outside this worktree.');
    }

    $app = require $root.'/bootstrap/app.php';
    $app->useEnvironmentPath($root);
    $app->loadEnvironmentFrom($environmentFile);
    $paths = [
        'base' => $app->basePath(),
        'storage' => $app->storagePath(),
        'config_cache' => $app->getCachedConfigPath(),
        'services_cache' => $app->getCachedServicesPath(),
        'packages_cache' => $app->getCachedPackagesPath(),
        'routes_cache' => $app->getCachedRoutesPath(),
        'events_cache' => $app->getCachedEventsPath(),
    ];
    foreach ($paths as $path) {
        if (! aiInside($path, $root)) {
            aiFail('An application/cache/storage path escapes the linked worktree.');
        }
    }
    $app->make(Kernel::class)->bootstrap();
    $config = $app->make('config');
    $actualDatabase = (string) $config->get('database.connections.sqlite.database');
    $safeDatabase = $testing ? $actualDatabase === ':memory:' : aiPath($actualDatabase) === aiPath($database);
    if ($app->environment() !== ($testing ? 'testing' : 'local')
        || $config->get('database.default') !== 'sqlite'
        || ! $safeDatabase || ! empty($config->get('database.connections.sqlite.url'))) {
        aiFail('Booted environment/database is unsafe. No selected command was executed.');
    }
    if (! aiInside((string) $config->get('view.compiled'), $root)
        || aiPath($app->storagePath()) !== aiPath($root.'/storage')) {
        aiFail('Booted view/storage path is unsafe. No selected command was executed.');
    }
    $key = Env::get('APP_KEY');
    if (! is_string($key) || $key === '' || $config->get('app.key') !== $key) {
        aiFail('Application key is missing or cached configuration disagrees with the selected environment.');
    }

    // Cached AI configuration must agree with the selected environment too.
    // Otherwise doctor could report AI off while Laravel uses a stale enabled flag.
    $expectedAi = require $root.'/config/ai.php';
    $expectedNutrition = require $root.'/config/nutrition.php';
    $aiEnabled = (bool) Env::get('AI_ENABLED', false);
    $aiKey = (string) Env::get('AI_GEMINI_KEY', '');
    if ($config->get('ai') !== $expectedAi
        || $config->get('nutrition') !== $expectedNutrition
        || $config->get('ai.enabled') !== $aiEnabled
        || (string) $config->get('ai.gemini.api_key', '') !== $aiKey
        || $config->get('ai.gemini.store') !== false
        || ($testing && ($aiEnabled || $aiKey !== ''))) {
        aiFail('Booted AI configuration is unsafe or stale. No selected command was executed.');
    }

    if ($command === 'doctor') {
        fwrite(STDOUT, json_encode([
            'branch' => 'feat/ai-mvp-4days',
            'environment_file' => $environmentFile,
            'environment' => $app->environment(),
            'database_driver' => 'sqlite',
            'database_target' => $database,
            'paths' => $paths,
            'session_cookie' => $config->get('session.cookie'),
            'ai_enabled' => $config->get('ai.enabled'),
            'ai_key_configured' => (string) $config->get('ai.gemini.api_key', '') !== '',
            'app_key_configured' => true,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
        exit(0);
    }
    if ($command === 'test') {
        $environmentCheck = $root.'/tools/ai-test-env-check.php';
        if (! is_file($environmentCheck)) {
            aiFail('The PHPUnit environment sentinel check is missing.');
        }
        $checkCode = aiRun([PHP_BINARY, $environmentCheck], $root);
        if ($checkCode !== 0) {
            exit($checkCode);
        }
    }
    if ($command === 'seed' && ! class_exists(AiPermissionSeeder::class)) {
        aiFail('AiPermissionSeeder is missing; the default/demo seeder will not be used.');
    }
    $processCommand = match ($command) {
        'migrate' => [PHP_BINARY, $root.'/artisan', 'migrate', '--database=sqlite', '--no-interaction'],
        'seed' => [PHP_BINARY, $root.'/artisan', 'db:seed', '--class=Database\\Seeders\\AiPermissionSeeder', '--no-interaction'],
        'test' => [PHP_BINARY, $root.'/artisan', 'test', ...$arguments],
        'lint' => [PHP_BINARY, $root.'/vendor/bin/pint', '--test', '--parallel'],
        'analyse' => [PHP_BINARY, $root.'/vendor/bin/phpstan', 'analyse', '--no-progress'],
        'serve' => [PHP_BINARY, $root.'/artisan', 'serve', '--host=127.0.0.1', '--port=8001'],
        'ai' => [PHP_BINARY, $root.'/artisan', ...$arguments],
    };
    exit(aiRun($processCommand, $root));
} catch (Throwable $error) {
    // Exception messages can contain dotenv lines, SQL values or credentials.
    fwrite(STDERR, 'Local setup command stopped ('.get_debug_type($error).'). No secret values were printed.'.PHP_EOL);
    exit(1);
}
