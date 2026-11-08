<?php

declare(strict_types=1);
use PHPUnit\TextUI\Configuration\PhpHandler;
use PHPUnit\TextUI\XmlConfiguration\Loader;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
foreach (['APP_ENV' => 'not-testing', 'DB_DATABASE' => 'SENTINEL_NEVER_OPEN_DATABASE', 'DB_URL' => 'SENTINEL_NEVER_CONNECT'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
}
$configuration = (new Loader)->load($root.'/phpunit.xml');
(new PhpHandler)->handle($configuration->php());
$checks = [
    'testing_environment_forced' => getenv('APP_ENV') === 'testing',
    'memory_database_forced' => getenv('DB_DATABASE') === ':memory:',
    'db_url_override_cleared' => getenv('DB_URL') === '',
];
echo json_encode($checks, JSON_PRETTY_PRINT).PHP_EOL;
exit(in_array(false, $checks, true) ? 1 : 0);
