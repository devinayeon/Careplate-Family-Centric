<?php

declare(strict_types=1);

// Helpers for tools/api-smoke.php. Nothing here prints cookies, tokens, passwords or keys.

use Illuminate\Contracts\Console\Kernel;

const SMOKE_BASE = 'http://127.0.0.1:8001';

function smokeFail(string $message): never
{
    throw new RuntimeException($message);
}

/** Boots the app against the git-ignored demo SQLite file only (same forced environment as tools/ai-dev.php). */
function smokeBoot(string $root): void
{
    $demo = $root.'/database/careplate-ai-demo.sqlite';
    if (! is_file($demo)) {
        smokeFail('Demo SQLite file is missing. Run: php tools/ai-dev.php migrate');
    }
    $forced = [
        'APP_ENV' => 'local', 'APP_URL' => SMOKE_BASE, 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $demo, 'DB_URL' => '',
        'LARAVEL_STORAGE_PATH' => $root.'/storage', 'QUEUE_CONNECTION' => 'sync',
        'APP_CONFIG_CACHE' => 'bootstrap/cache/config.php', 'APP_SERVICES_CACHE' => 'bootstrap/cache/services.php',
        'APP_PACKAGES_CACHE' => 'bootstrap/cache/packages.php', 'APP_ROUTES_CACHE' => 'bootstrap/cache/routes-v7.php',
        'APP_EVENTS_CACHE' => 'bootstrap/cache/events.php',
    ];
    foreach ($forced as $name => $value) {
        putenv($name.'='.$value);
        $_ENV[$name] = $_SERVER[$name] = $value;
    }
    require_once $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $actual = (string) config('database.connections.sqlite.database');
    if (realpath($actual) !== realpath($demo)) {
        smokeFail('Booted database is not the demo file; stopping.');
    }
}

/** @return array{0: resource, 1: array<int, resource>, 2: int, 3: string} */
function smokeServerStart(string $root): array
{
    $log = sys_get_temp_dir().DIRECTORY_SEPARATOR.'smoke-server-'.bin2hex(random_bytes(4)).'.log';
    $pipes = [];
    $process = proc_open([PHP_BINARY, $root.'/tools/ai-dev.php', 'serve'], [['file', 'php://stdin', 'r'], ['file', $log, 'w'], ['file', $log, 'a']], $pipes, $root);
    if (! is_resource($process)) {
        smokeFail('Could not start the dev server.');
    }
    $pid = (int) (proc_get_status($process)['pid'] ?? 0);
    for ($i = 0; $i < 60; $i++) {
        usleep(500000);
        if (! (proc_get_status($process)['running'] ?? false)) {
            smokeFail('Dev server exited early (is port 8001 already in use?).');
        }
        $ch = curl_init(SMOKE_BASE.'/up');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2]);
        curl_exec($ch);
        $up = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200;
        curl_close($ch);
        if ($up) {
            return [$process, $pipes, $pid, $log];
        }
    }
    smokeFail('Dev server did not answer /up within 30 s.');
}

function smokeServerStop(mixed $process, int $pid, string $log): void
{
    if (is_resource($process)) {
        // php artisan serve spawns the real php -S child: stop the whole tree.
        if (PHP_OS_FAMILY === 'Windows') {
            exec('taskkill /F /T /PID '.$pid.' >NUL 2>&1');
        } else {
            proc_terminate($process);
        }
        proc_close($process);
    }
    if (is_file($log)) {
        @unlink($log);
    }
}

/** One browser-like client: its own cookie jar (outside the repo), CSRF header from the URL-decoded XSRF-TOKEN cookie. */
final class SmokeClient
{
    public readonly string $jar;

    public function __construct()
    {
        $this->jar = sys_get_temp_dir().DIRECTORY_SEPARATOR.'smoke-jar-'.bin2hex(random_bytes(6)).'.txt';
        touch($this->jar);
    }

    public function destroy(): void
    {
        if (is_file($this->jar)) {
            @unlink($this->jar);
        }
    }

    private function xsrf(): ?string
    {
        foreach (file($this->jar, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $fields = explode("\t", $line);
            if (count($fields) === 7 && $fields[5] === 'XSRF-TOKEN') {
                return urldecode($fields[6]);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @param  array<string, mixed>|null  $multipart
     * @return array{0: int, 1: array<string, mixed>|null}
     */
    public function send(string $method, string $path, ?array $json = null, ?array $multipart = null, int $timeout = 60): array
    {
        $headers = ['Accept: application/json'];
        if ($method !== 'GET' && ($token = $this->xsrf()) !== null) {
            $headers[] = 'X-XSRF-TOKEN: '.$token;
        }
        $ch = curl_init(SMOKE_BASE.$path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => $timeout,
        ]);
        if ($json !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_THROW_ON_ERROR));
        } elseif ($multipart !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $decoded = is_string($body) ? json_decode($body, true) : null;

        return [$status, is_array($decoded) ? $decoded : null];
    }

    /** CSRF cookie, then login without following the redirect. Returns the login status. */
    public function login(string $email, string $password): int
    {
        [$csrf] = $this->send('GET', '/sanctum/csrf-cookie');
        if ($csrf !== 204 || $this->xsrf() === null) {
            smokeFail("csrf-cookie returned $csrf or set no XSRF-TOKEN.");
        }

        return $this->send('POST', '/login', ['email' => $email, 'password' => $password])[0];
    }
}
