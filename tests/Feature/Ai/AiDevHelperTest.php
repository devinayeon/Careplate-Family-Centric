<?php

use Illuminate\Support\Facades\Process;

function runAiDev(string ...$arguments): array
{
    $result = Process::path(base_path())->run([PHP_BINARY, 'tools/ai-dev.php', ...$arguments]);

    return [$result->exitCode(), $result->errorOutput()];
}

test('ai passthrough refuses environment and database overrides', function (array $extra) {
    [$code, $error] = runAiDev('ai', 'ai:foo', ...$extra);

    expect($code)->toBe(1)
        ->and($error)->toContain('--env and --database options are not accepted');
})->with([
    'env equals' => [['--env=x']],
    'env prefix' => [['--envx=prod']],
    'env separate value' => [['--env', 'x']],
    'database equals' => [['--database=x']],
    'database prefix' => [['--databasefoo=x']],
    'after other args' => [['--verbose', '--envfoo=production']],
]);

test('ai passthrough refuses names outside the ai namespace', function (string $name) {
    [$code, $error] = runAiDev('ai', $name);

    expect($code)->toBe(1)
        ->and($error)->toContain('only runs artisan commands named ai:');
})->with(['migrate', 'ai', 'db:wipe', 'ai:foo;ls', '--help']);

test('ai passthrough without a command is refused', function () {
    [$code] = runAiDev('ai');

    expect($code)->toBe(1);
});
