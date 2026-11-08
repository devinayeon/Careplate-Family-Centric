<?php

declare(strict_types=1);

// Live API smoke: php tools/api-smoke.php  (needs the demo DB: php tools/ai-dev.php migrate / ai:import-foods / ai:seed-catalog,
// and a configured provider in .env for the one scan + chat calls). Starts `php tools/ai-dev.php serve` on 127.0.0.1:8001 itself.
// HTTP is plain curl; Laravel is only booted to create/delete throwaway users. Prints status codes and sanitised summaries only.

use App\Models\ChatSession;
use App\Models\FoodSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

if (PHP_SAPI !== 'cli') {
    exit(2);
}
$root = dirname(__DIR__);
require __DIR__.'/api-smoke-lib.php';

function say(string $line): void
{
    fwrite(STDOUT, $line.PHP_EOL);
}

/** Asserts the status, prints one sanitised line, returns the decoded body. */
function expectStatus(string $label, array $result, int|array $expected, string $summary = ''): ?array
{
    [$status, $body] = $result;
    $ok = in_array($status, (array) $expected, true);
    $code = is_array($body['error'] ?? null) ? ' error='.($body['error']['code'] ?? '?') : '';
    say(sprintf('%-4s %-34s %d%s%s', $ok ? 'ok' : 'FAIL', $label, $status, $code, $summary !== '' ? '  '.$summary : ''));
    if (! $ok) {
        smokeFail("Unexpected status $status for: $label");
    }

    return $body;
}

function makeUser(string $label, string $role, string $password): User
{
    $user = User::create(['name' => 'Smoke '.$label, 'email' => 'smoke-'.$label.'-'.bin2hex(random_bytes(4)).'@example.test', 'password' => $password]);
    $user->assignRole($role);

    return $user;
}

$process = null;
$pid = 0;
$log = '';
$users = [];
$clients = [];
$tag = bin2hex(random_bytes(4));
$exit = 0;
try {
    smokeBoot($root);
    [$process, , $pid, $log] = smokeServerStart($root);
    say('server up on '.SMOKE_BASE);

    $password = bin2hex(random_bytes(12));
    foreach (['a' => 'family_head', 'b' => 'family_head', 'admin' => 'admin'] as $label => $role) {
        $users[$label] = makeUser($label, $role, $password);
        $clients[$label] = new SmokeClient;
        expectStatus("login $label", [$clients[$label]->login($users[$label]->email, $password), null], [200, 204, 302]);
    }
    [$a, $b, $admin] = [$clients['a'], $clients['b'], $clients['admin']];

    $images = glob($root.'/ai/evals/ocr/images/*') ?: [];
    if ($images === []) {
        smokeFail('No sample image under ai/evals/ocr/images.');
    }
    $scan = expectStatus('scan (1 live provider call)', $a->send('POST', '/ai/v1/scan', null, ['image' => new CURLFile($images[0])], 120), 200);
    say('     scan top-level keys: '.implode(',', array_keys($scan ?? [])));

    $session = expectStatus('create chat session', $a->send('POST', '/ai/v1/chat/sessions', []), 201);
    $sid = (string) $session['id'];
    $turnsPath = "/ai/v1/chat/sessions/$sid/turns";

    $dbKey = (string) Str::uuid();
    $dbBody = ['message' => 'Berapa protein dalam 100 gram dada ayam tanpa kulit mentah?', 'client_turn_id' => $dbKey];
    $db = expectStatus('turn 1 database-mode', $a->send('POST', $turnsPath, $dbBody, null, 120), 200);
    say(sprintf('     mode=%s retrieval=%s sequence=%d citations=%d', $db['mode'], $db['retrieval_status'], $db['sequence'], count($db['citations'])));
    if ($db['mode'] !== 'database') {
        smokeFail('Expected mode=database for the catalog question.');
    }

    $general = expectStatus('turn 2 general', $a->send('POST', $turnsPath, ['message' => 'Apa fungsi protein bagi tubuh?', 'client_turn_id' => (string) Str::uuid()], null, 120), 200);
    say(sprintf('     mode=%s sequence=%d notice=%s', $general['mode'], $general['sequence'], $general['general_knowledge_notice'] !== null ? 'yes' : 'no'));
    if ($general['mode'] !== 'general') {
        smokeFail('Expected mode=general.');
    }

    $started = microtime(true);
    $replay = expectStatus('replay turn 1 (same client_turn_id)', $a->send('POST', $turnsPath, $dbBody), 200);
    $same = $replay === $db;
    say(sprintf('     identical stored response=%s same turn_id/sequence=%s elapsed=%dms (no provider latency => no 2nd call)',
        $same ? 'yes' : 'NO', ($replay['turn_id'] === $db['turn_id'] && $replay['sequence'] === $db['sequence']) ? 'yes' : 'NO', (microtime(true) - $started) * 1000));
    $conflict = expectStatus('same key, different message', $a->send('POST', $turnsPath, ['message' => 'Pesan lain?', 'client_turn_id' => $dbKey]), 409);
    if (! $same || ($conflict['error']['code'] ?? '') !== 'IDEMPOTENCY_CONFLICT') {
        smokeFail('Replay was not identical or the changed-body replay was not IDEMPOTENCY_CONFLICT.');
    }

    $history = expectStatus('list history', $a->send('GET', $turnsPath), 200);
    say('     turns='.count($history['data']).' states='.implode(',', array_column($history['data'], 'state')));
    $list = expectStatus('list sessions', $a->send('GET', '/ai/v1/chat/sessions'), 200);
    say('     sessions='.count($list['data']));

    expectStatus('B reads A session turns', $b->send('GET', $turnsPath), 404);
    expectStatus('B sends turn to A session', $b->send('POST', $turnsPath, ['message' => 'Halo', 'client_turn_id' => (string) Str::uuid()]), 404);
    expectStatus('B deletes A session', $b->send('DELETE', "/ai/v1/chat/sessions/$sid"), 404);

    $name = 'Smoke Uji '.$tag;
    $submission = expectStatus('A submits food (MANUAL)', $a->send('POST', '/ai/v1/food-submissions', [
        'name' => $name, 'aliases' => [strtolower($name)], 'basis' => '100g',
        'nutrients' => ['energy_kcal' => 80, 'protein_g' => 10, 'fat_g' => 4.8, 'carbohydrate_g' => 1.9, 'sodium_mg' => 7, 'sugars_total_g' => null],
        'source' => ['provider' => 'MANUAL', 'source_id' => 'smoke-'.$tag, 'source_url' => 'https://example.test/smoke/'.$tag, 'snapshot_version' => 'smoke-'.$tag, 'source_sha256' => null],
    ]), 201);
    say('     status='.$submission['status'].' revision_counter='.$submission['revision_counter']);

    $approve = expectStatus('admin approve (not in manifest)', $admin->send('POST', '/ai/v1/admin/food-submissions/'.$submission['submission_id'].'/approve', ['expected_revision' => 1, 'reviewer_note' => 'Uji asap']), [422, 409]);
    say('     refused: '.($approve['error']['code'] ?? '?').' '.($approve['error']['message'] ?? ''));
    expectStatus('A cannot approve (403)', $a->send('POST', '/ai/v1/admin/food-submissions/'.$submission['submission_id'].'/approve', ['expected_revision' => 1, 'reviewer_note' => 'x']), 403);

    $foods = expectStatus('GET /foods?q=smoke name', $a->send('GET', '/ai/v1/foods?q='.rawurlencode($name)), 200);
    $catalog = expectStatus('GET /foods?q=ayam', $a->send('GET', '/ai/v1/foods?q=ayam'), 200);
    say(sprintf('     pending submission visible in /foods=%s; catalog hits for "ayam"=%d', count($foods['data']) === 0 ? 'no' : 'YES', count($catalog['data'])));
    if (count($foods['data']) !== 0 || count($catalog['data']) < 1) {
        smokeFail('Pending submission leaked into /foods, or the catalog returned nothing.');
    }

    expectStatus('A deletes session', $a->send('DELETE', "/ai/v1/chat/sessions/$sid"), 204);
    expectStatus('deleted session turns', $a->send('GET', $turnsPath), 404);
    say('SMOKE OK');
} catch (Throwable $error) {
    say('SMOKE FAILED: '.$error->getMessage());
    $exit = 1;
} finally {
    if ($users !== []) {
        try {
            $ids = array_map(fn (User $user) => $user->getKey(), $users);
            FoodSubmission::query()->whereIn('user_id', $ids)->orWhere('source_id', 'smoke-'.$tag)->delete();
            ChatSession::query()->whereIn('user_id', $ids)->delete();
            foreach ($users as $user) {
                DB::table('model_has_roles')->where('model_id', $user->getKey())->where('model_type', User::class)->delete();
                $user->delete();
            }
            say(sprintf('cleanup: users left=%d sessions left=%d submissions left=%d', User::whereIn('id', $ids)->count(),
                ChatSession::whereIn('user_id', $ids)->count(), FoodSubmission::where('source_id', 'smoke-'.$tag)->count()));
        } catch (Throwable $error) {
            say('cleanup FAILED ('.get_debug_type($error).'); remove users smoke-*@example.test by hand.');
            $exit = 1;
        }
    }
    foreach ($clients as $client) {
        $client->destroy();
    }
    smokeServerStop($process, $pid, $log);
}
exit($exit);
