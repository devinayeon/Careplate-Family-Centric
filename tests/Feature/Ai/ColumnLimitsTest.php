<?php

use App\Actions\Foods\ReviewFood;
use App\Models\ChatTurn;
use App\Models\Food;
use App\Models\FoodRevision;
use App\Models\FoodSubmission;

require_once __DIR__.'/FoodSubmissionSupport.php';
require_once __DIR__.'/DbPortabilitySupport.php';

// Every value the request validation / importer / ReviewFood accepts must fit its column (MySQL strict mode
// raises "Data too long" or rounds silently, SQLite ignores both). SYNTHETIC data only.

beforeEach(function () {
    chatSetUp();
    chatFake();
});

function clUrl(int $length): string
{
    return 'https://example.test/'.str_repeat('a', $length - strlen('https://example.test/'));
}

function clApprove(FoodSubmission $s, string $audit = 'user:admin', string $note = 'Sumber dicocokkan.'): array
{
    return app(ReviewFood::class)->approve($s->id, null, $audit, $s->revision_counter, $note, true);
}

test('a submission at every validation maximum is stored and read back unchanged', function () {
    fsManifest();
    $body = fsBody([
        'name' => str_repeat('é', 120),
        'aliases' => array_map(fn ($i) => str_repeat('ñ', 79).$i, range(1, 8)),
        'nutrients' => array_fill_keys(['energy_kcal', 'protein_g', 'fat_g', 'carbohydrate_g', 'sodium_mg', 'sugars_total_g'], '100000.0000'),
        'source' => ['source_id' => '900001', 'source_url' => clUrl(500), 'snapshot_version' => str_repeat('v', 100)],
    ]);

    $row = FoodSubmission::query()->findOrFail(fsSubmit(fsUser(), $body)->assertCreated()->json('submission_id'));

    expect(mb_strlen($row->source_url))->toBe(500)->and($row->source_url)->toBe(clUrl(500))
        ->and($row->snapshot_version)->toBe(str_repeat('v', 100))
        ->and($row->candidate['name'])->toBe(str_repeat('é', 120))
        ->and($row->candidate['aliases'])->toBe($body['aliases']);
});

test('a maximum submission approves into foods with its 500-character url and 100-character source id', function () {
    $sourceId = str_repeat('9', 100);
    fsManifest([$sourceId]);
    $submission = FoodSubmission::query()->findOrFail(fsSubmit(fsUser(), fsBody([
        'name' => str_repeat('é', 120),
        'nutrients' => array_fill_keys(['energy_kcal', 'protein_g', 'fat_g', 'carbohydrate_g', 'sodium_mg', 'sugars_total_g'], '100000.0000'),
        'source' => ['source_id' => $sourceId, 'source_url' => clUrl(500)],
    ]))->assertCreated()->json('submission_id'));

    clApprove($submission);

    $food = Food::query()->sole();
    expect($food->source_url)->toBe(clUrl(500))->and($food->source_id)->toBe($sourceId)
        ->and($food->name)->toBe(str_repeat('é', 120))->and($food->protein_g)->toBe('100000.0000');
});

test('the largest stored nutrient value round-trips exactly and an extra decimal is refused, never rounded', function () {
    rfManifest();
    $max = rfSubmission([], ['nutrients' => ['protein_g' => '99999999.9999']]);
    clApprove($max);
    expect(Food::query()->sole()->protein_g)->toBe('99999999.9999');

    foreach (['0.00005', '1.12345678', '7.00001'] as $i => $value) {
        $submission = rfSubmission(['source_id' => '90000'.($i + 2)], ['nutrients' => ['fat_g' => $value]]);
        $e = rfThrown(fn () => clApprove($submission));
        expect([$e->errorCode, $e->status])->toBe(['INVALID_INPUT', 422])->and($e->getMessage())->toContain('CANDIDATE_INVALID');
    }
    expect(Food::query()->count())->toBe(1);
});

test('candidate text beyond its column is refused as CANDIDATE_INVALID instead of a database error', function (array $candidate) {
    rfManifest();
    $submission = rfSubmission([], $candidate);

    $e = rfThrown(fn () => clApprove($submission));

    expect($e->errorCode)->toBe('INVALID_INPUT')->and($e->getMessage())->toContain('CANDIDATE_INVALID')
        ->and(Food::query()->count())->toBe(0);
})->with([
    'energy_method over 255' => [['nutrients' => ['energy_method' => str_repeat('m', 256)]]],
    'description over 10000' => [['source_description' => str_repeat('d', 10001)]],
    'more than 20 aliases' => [['aliases' => array_map(fn ($i) => "alias {$i}", range(1, 21))]],
    'alias over 255' => [['aliases' => [str_repeat('a', 256)]]],
]);

test('candidate text at its limit approves and is stored in full', function () {
    rfManifest();
    $submission = rfSubmission([], [
        'name' => str_repeat('é', 255), 'aliases' => array_fill(0, 20, str_repeat('ñ', 255)),
        'nutrients' => ['energy_method' => str_repeat('m', 255)], 'source_description' => str_repeat('d', 10000),
    ]);

    clApprove($submission);

    $food = Food::query()->sole();
    expect(mb_strlen($food->name))->toBe(255)->and($food->aliases)->toHaveCount(20)
        ->and(mb_strlen($food->energy_method))->toBe(255)->and(mb_strlen($food->source_description))->toBe(10000)
        ->and(mb_strlen((string) $food->search_text))->toBeGreaterThan(0);
});

test('audit id and note: the maximum is stored, one more is INVALID_INPUT', function () {
    rfManifest();
    $audit = 'user:'.str_repeat('a', 250);
    $result = clApprove(rfSubmission(), $audit, str_repeat('n', 1000));

    $food = Food::query()->findOrFail($result['food_id']);
    $revision = FoodRevision::query()->sole();
    expect(strlen($food->verifier_audit_id))->toBe(255)->and(strlen($revision->actor_audit_id))->toBe(255)
        ->and(mb_strlen($revision->note))->toBe(1000);

    foreach ([['x'.str_repeat('a', 255), 'ok'], ['user:admin', str_repeat('n', 1001)]] as [$longAudit, $note]) {
        $e = rfThrown(fn () => app(ReviewFood::class)->revoke($food, null, $longAudit, 1, $note));
        expect([$e->errorCode, $e->status])->toBe(['INVALID_INPUT', 422]);
    }
    expect($food->fresh()->status)->toBe('verified');
});

test('a chat turn at the maximum message length and amount is stored in full', function () {
    [$user, $session] = chatActor();
    $message = str_repeat('é', (int) config('ai.chat.max_message_characters'));

    $json = chatAsk($user, $session, $message, 1, ['amount_g' => '1999.99'])->assertOk()->json();

    $turn = ChatTurn::query()->findOrFail($json['turn_id']);
    expect(mb_strlen($turn->message))->toBe(2000)->and($turn->amount_g)->toBe('1999.99')
        ->and(strlen($turn->request_hash))->toBe(64)->and(strlen($turn->token))->toBe(64);
});
