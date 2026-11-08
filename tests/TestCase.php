<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

require_once __DIR__.'/../tools/ai-test-mysql-guard.php';

abstract class TestCase extends BaseTestCase
{
    /** Last line of defence: a MySQL run may only ever touch the throwaway 127.0.0.1:3407 *_test / *_cc database. */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();
        $name = config('database.default');
        if (config("database.connections.$name.driver") === 'mysql') {
            $c = config("database.connections.$name");
            $refusal = aiMysqlTargetError('mysql', $c['host'] ?? null, $c['port'] ?? null, $c['database'] ?? null,
                $c['unix_socket'] ?? '', $c['url'] ?? '');
            if ($refusal !== null) {
                throw new RuntimeException($refusal);
            }
        }
    }
}
