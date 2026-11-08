<?php

use App\Models\ChatTurn;
use App\Services\Ai\Chat\SessionEraser;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/ChatApiSupport.php';

beforeEach(function () {
    chatSetUp();
    chatFake();
});

// The session is deleted in the gap between finalize's commit and ChatService reading the stored answer back.
// Same answer as any other late result: 404 NOT_FOUND, and the erased turn is never written again.
test('a delete committed between finalize and the read-back is NOT_FOUND, never a 500 and never resurrected', function () {
    cfFood(['name' => 'Tahu contoh', 'protein_g' => '10']);
    [$user, $session] = chatActor();
    $race = (object) ['armed' => false, 'ran' => false];
    DB::listen(function ($query) use ($race) {
        if (preg_match('/^update [`"]chat_turns[`"]/', $query->sql) === 1 && in_array('completed', $query->bindings, true)) {
            $race->armed = true; // finalize just wrote the answer; the next read of chat_turns is the read-back
        }
    });
    DB::connection()->beforeExecuting(function (string $sql) use ($race, $session) {
        if ($race->armed && ! $race->ran && preg_match('/^select \* from [`"]chat_turns[`"]/', $sql) === 1) {
            $race->ran = true;
            app(SessionEraser::class)->erase($session);
        }
    });

    $response = chatAsk($user, $session, 'Berapa protein tahu contoh?');

    chatError($response, 404, 'NOT_FOUND');
    $turn = ChatTurn::query()->sole();
    expect($race->ran)->toBeTrue()
        ->and($turn->response)->toBeNull()->and($turn->citations)->toBeNull()->and($turn->message)->toBeNull()
        ->and($turn->status)->toBe('completed')
        ->and(DB::table('chat_sessions')->value('state'))->toBe('deleted');
});
