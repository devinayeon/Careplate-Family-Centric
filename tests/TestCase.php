<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

require_once __DIR__.'/../tools/ai-test-mysql-guard.php';

abstract class TestCase extends BaseTestCase
{
    /** Last line of defence: a MySQL or MariaDB run may only ever touch the throwaway 127.0.0.1:3407 *_test / *_cc database. */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();
        $refusal = aiTestConnectionError((array) config('database.connections.'.config('database.default')));
        if ($refusal !== null) {
            throw new RuntimeException($refusal);
        }
    }
}
