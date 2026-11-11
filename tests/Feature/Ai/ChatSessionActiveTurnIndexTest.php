<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

it('indexes chat_sessions.active_turn_id so the purge update does not scan every session row', function () {
    expect(Schema::hasIndex('chat_sessions', ['active_turn_id']))->toBeTrue();
});

it('rolls the active_turn_id index back and forward again', function () {
    $path = 'database/migrations/2026_11_09_000002_index_chat_sessions_active_turn_id.php';

    Artisan::call('migrate:rollback', ['--path' => $path, '--force' => true]);
    expect(Schema::hasIndex('chat_sessions', ['active_turn_id']))->toBeFalse();

    Artisan::call('migrate', ['--path' => $path, '--force' => true]);
    expect(Schema::hasIndex('chat_sessions', ['active_turn_id']))->toBeTrue();
});
