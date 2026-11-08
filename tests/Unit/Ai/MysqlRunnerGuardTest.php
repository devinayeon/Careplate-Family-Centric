<?php

// Offline checks of the MySQL test-target guard (tools/ai-test-mysql-guard.php). No connection is opened.

require_once __DIR__.'/../../../tools/ai-test-mysql-guard.php';

test('the throwaway targets are allowed', function (string $database) {
    expect(aiMysqlTargetError('mysql', '127.0.0.1', '3407', $database))->toBeNull()
        ->and(aiMysqlTargetError('mysql', '127.0.0.1', 3407, $database))->toBeNull();
})->with(['careplate_ai_test', 'careplate_ai_cc']);

test('any other target is refused', function (array $target) {
    expect(aiMysqlTargetError(...$target))->toBeString()->toStartWith('Refused');
})->with([
    'default mysql port' => [['mysql', '127.0.0.1', '3306', 'careplate_ai_test']],
    'other host' => [['mysql', 'localhost', '3407', 'careplate_ai_test']],
    'remote host' => [['mysql', '10.0.0.5', '3407', 'careplate_ai_test']],
    'production-like name' => [['mysql', '127.0.0.1', '3407', 'careplate']],
    'suffix only mid-name' => [['mysql', '127.0.0.1', '3407', 'careplate_test_data']],
    'empty name' => [['mysql', '127.0.0.1', '3407', '']],
    'missing name' => [['mysql', '127.0.0.1', '3407', null]],
    'injected name' => [['mysql', '127.0.0.1', '3407', 'x`; drop database y; -- _test']],
    'sqlite connection' => [['sqlite', '127.0.0.1', '3407', 'careplate_ai_test']],
    'socket override' => [['mysql', '127.0.0.1', '3407', 'careplate_ai_test', '/tmp/mysql.sock']],
    'url override' => [['mysql', '127.0.0.1', '3407', 'careplate_ai_test', '', 'mysql://prod/db']],
]);

test('phpunit.mysql.xml pins the target and the runner refuses unsupported options', function () {
    // force="true" is what beats any host DB_* variable; the runner also scrubs DB_*/APP_* from the child.
    $root = dirname(__DIR__, 3);
    $xml = (string) file_get_contents($root.'/phpunit.mysql.xml');
    foreach ([['DB_HOST', '127.0.0.1'], ['DB_PORT', '3407'], ['DB_DATABASE', 'careplate_ai_test'],
        ['DB_SOCKET', ''], ['DB_URL', ''], ['DB_CONNECTION', 'mysql']] as [$name, $value]) {
        expect($xml)->toContain('<env name="'.$name.'" value="'.$value.'" force="true"/>');
    }
    $output = [];
    $code = 0;
    exec('php '.escapeshellarg($root.'/tools/ai-test-mysql.php').' --bogus 2>&1', $output, $code);
    expect($code)->toBe(1)->and(implode("\n", $output))->toContain('Unsupported test option');
});
