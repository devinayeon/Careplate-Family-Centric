<?php

use Database\Seeders\AiPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

require_once __DIR__.'/ChatApiSupport.php';
require_once __DIR__.'/GeminiTestSupport.php';

const DB_MARKER = 'CANARY-CHAT-MARKER-71c4d9';

beforeEach(function () {
    test()->seed(AiPermissionSeeder::class);
    Http::fake();
});

/** A non-lock database error whose message embeds its bindings, as Laravel's QueryException does. */
function dbMarkerError(): QueryException
{
    $e = new QueryException('sqlite', 'insert into "chat_turns" ("message") values (?)', [DB_MARKER], new PDOException('disk I/O error'));
    expect($e->getMessage())->toContain(DB_MARKER);

    return $e;
}

it('keeps chat text out of the logs when a database error is reported on an ai route', function () {
    [$logged] = captureLogs();
    [$user, $session] = chatActor();
    DB::listen(function ($query) {
        if (str_starts_with($query->sql, 'insert into "chat_turns"')) {
            throw dbMarkerError();
        }
    });

    $response = chatAsk($user, $session, 'Berapa protein '.DB_MARKER.'?');

    chatError($response, 500, 'INTERNAL');
    expect($response->getContent())->not->toContain(DB_MARKER);
    $lines = implode("\n", $logged->getArrayCopy());
    expect($lines)->not->toContain(DB_MARKER)->and($lines)->not->toContain('chat_turns')
        ->and($lines)->toContain('ai.database_error')->toContain('QueryException')
        ->toContain($response->json('request_id'));
});

it('keeps default reporting for the same error on a non-ai route', function () {
    [$logged] = captureLogs();
    Route::get('/_db_probe', fn () => throw dbMarkerError());

    test()->getJson('/_db_probe')->assertStatus(500);

    expect(implode("\n", $logged->getArrayCopy()))->toContain(DB_MARKER);
});
