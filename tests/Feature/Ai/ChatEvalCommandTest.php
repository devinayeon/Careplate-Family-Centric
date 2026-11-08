<?php

use App\Models\ChatSession;
use App\Models\ChatTurn;
use App\Models\Food;
use App\Models\User;
use App\Services\Ai\Chat\ChatService;
use App\Services\Ai\Eval\ChatComparer;
use App\Services\Ai\Eval\ChatEvalRunner;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

require_once __DIR__.'/ChatApiSupport.php';

// SYNTHETIC catalog, users and provider replies only. The real demo catalog is never touched by tests.

beforeEach(fn () => chatSetUp());

function chatEvalDir(): string
{
    $dir = sys_get_temp_dir().'/ai-chat-eval-test-'.bin2hex(random_bytes(4));
    mkdir($dir);

    return $dir;
}

/** The two foods the case map names, with SYNTHETIC values (carrot has no total sugars, like the demo row). */
function chatEvalCatalog(): void
{
    cfFood(['name' => 'Dada ayam tanpa kulit mentah', 'protein_g' => '22.5', 'energy_kcal' => '112', 'fat_g' => '1.93', 'carbohydrate_g' => '0', 'sodium_mg' => '65.8', 'sugars_total_g' => null]);
    cfFood(['name' => 'Wortel mentah', 'protein_g' => '0.941', 'energy_kcal' => '45', 'fat_g' => '0.351', 'carbohydrate_g' => '10.3', 'sodium_mg' => '86.6', 'sugars_total_g' => null]);
}

/** @return array<string, mixed> */
function chatEvalResult(string $dir): array
{
    $files = glob($dir.'/chat-*.json');
    expect($files)->toHaveCount(1);

    return json_decode(file_get_contents($files[0]), true);
}

function chatEvalItem(array $result, string $id): array
{
    return collect($result['items'])->firstWhere('id', $id);
}

function chatEvalNothingLeft(): void
{
    expect(User::count())->toBe(0)->and(ChatSession::count())->toBe(0)->and(ChatTurn::count())->toBe(0);
}

test('the case map covers every golden case and the golden file keeps its 12 cases', function () {
    $cases = ChatEvalRunner::loadCases(base_path('ai/evals/chat-golden.jsonl'), base_path('ai/evals/chat-catalog-map.json'));

    expect(array_column($cases, 'id'))->toBe(array_map(fn ($n) => sprintf('CHAT-%02d', $n), range(1, 12)))
        ->and(array_column(array_column($cases, 'golden'), 'status'))->each->toBe('specified_not_run');
    foreach ($cases as $case) {
        expect($case)->toHaveKey($case['applicable'] ? 'expect' : 'reason');
    }
});

test('ai:eval chat runs the golden cases, calls the model only for general cases, and cleans up', function () {
    chatFake();
    chatEvalCatalog();
    $foods = Food::count();
    $dir = chatEvalDir();

    $this->artisan('ai:eval', ['suite' => 'chat', '--out-dir' => $dir])->assertSuccessful();

    $raw = file_get_contents(glob($dir.'/chat-*.json')[0]);
    $result = json_decode($raw, true);
    expect($result['summary'])->toMatchArray(['cases' => 12, 'applicable' => 9, 'not_applicable' => 3, 'skipped' => 0, 'routing' => '9/9', 'passed' => '9/9', 'unsafe_claims' => 0, 'errors' => 0, 'model_calls' => 2])
        ->and($result['model_requests'])->toBe(2)
        ->and(collect($result['items'])->where('model_calls', '>', 0)->pluck('id')->all())->toBe(['CHAT-02', 'CHAT-10'])
        ->and(collect($result['items'])->where('applicable', true)->where('group', '!=', 'general')->where('group', '!=', 'injection')->pluck('model_calls')->unique()->all())->toBe([0])
        ->and(chatEvalItem($result, 'CHAT-01')['claims'])->toBe([['nutrient' => 'protein_g', 'value' => '11.25']])
        ->and(chatEvalItem($result, 'CHAT-01')['citations'][0]['revision'])->toBe(3)
        ->and(chatEvalItem($result, 'CHAT-05')['claims'])->toBe([])
        ->and(chatEvalItem($result, 'CHAT-07')['claims'])->toHaveCount(5)
        ->and(chatEvalItem($result, 'CHAT-04')['reason'])->toContain('Beras putih mentah')
        ->and(chatEvalItem($result, 'CHAT-04')['outcome'])->toBe('not_applicable')
        ->and(chatEvalItem($result, 'CHAT-02')['usage'])->toBe(['prompt' => 11, 'candidates' => 7, 'thoughts' => 3, 'total' => 21])
        ->and($result['catalog'])->toHaveCount(2)
        ->and($raw)->not->toContain(GEMINI_FAKE_KEY)->not->toContain('base64');
    Http::assertSentCount(2);
    expect(Food::count())->toBe($foods);
    chatEvalNothingLeft();
    File::deleteDirectory($dir);
});

test('database, clarification, no_match and referral cases make zero provider calls', function () {
    chatFake();
    chatEvalCatalog();
    $dir = chatEvalDir();

    $this->artisan('ai:eval', ['suite' => 'chat', '--out-dir' => $dir])->assertSuccessful();

    $result = chatEvalResult($dir);
    foreach (['CHAT-01', 'CHAT-03', 'CHAT-05', 'CHAT-06', 'CHAT-07', 'CHAT-11', 'CHAT-12'] as $id) {
        $item = chatEvalItem($result, $id);
        expect($item['model_calls'])->toBe(0)->and($item['usage'])->toBeNull()->and($item['pass'])->toBeTrue();
    }
    expect(chatEvalItem($result, 'CHAT-11')['actual_mode'])->toBe('referral')
        ->and(chatEvalItem($result, 'CHAT-06')['actual_mode'])->toBe('clarification')
        ->and(chatEvalItem($result, 'CHAT-03')['actual_retrieval_status'])->toBe('no_match');
    File::deleteDirectory($dir);
});

test('without AI the model-free cases still run and the model cases are skipped', function () {
    config(['ai.enabled' => false, 'ai.gemini.api_key' => '']);
    Http::fake();
    chatEvalCatalog();
    $dir = chatEvalDir();

    $this->artisan('ai:eval', ['suite' => 'chat', '--out-dir' => $dir])->assertSuccessful();

    Http::assertNothingSent();
    $result = chatEvalResult($dir);
    expect($result['summary'])->toMatchArray(['applicable' => 7, 'skipped' => 2, 'not_applicable' => 3, 'model_calls' => 0, 'routing' => '7/7'])
        ->and(chatEvalItem($result, 'CHAT-02')['outcome'])->toBe('skipped')
        ->and(chatEvalItem($result, 'CHAT-10')['skipped'])->toBe('ai_disabled');
    chatEvalNothingLeft();
    File::deleteDirectory($dir);
});

test('a case whose food is missing from the catalog is not applicable, never scored', function () {
    chatFake();
    $dir = chatEvalDir();

    $this->artisan('ai:eval', ['suite' => 'chat', '--out-dir' => $dir])->assertSuccessful();

    $result = chatEvalResult($dir);
    expect(chatEvalItem($result, 'CHAT-01')['outcome'])->toBe('not_applicable')
        ->and(chatEvalItem($result, 'CHAT-01')['reason'])->toContain('Dada ayam tanpa kulit mentah')
        ->and($result['summary']['applicable'])->toBe(5)
        ->and($result['summary']['not_applicable'])->toBe(7);
    File::deleteDirectory($dir);
});

test('a failing case is reported and the command still succeeds', function () {
    Http::swap(new Factory);
    Http::fake([GEMINI_URL => Http::response(geminiOk(['wrong' => 'shape']))]);
    chatEvalCatalog();
    $dir = chatEvalDir();

    $this->artisan('ai:eval', ['suite' => 'chat', '--out-dir' => $dir])->assertSuccessful();

    $result = chatEvalResult($dir);
    expect(chatEvalItem($result, 'CHAT-02')['error_code'])->toBe('AI_OUTPUT_INVALID')
        ->and(chatEvalItem($result, 'CHAT-02')['pass'])->toBeFalse()
        ->and($result['summary']['errors'])->toBeGreaterThanOrEqual(1);
    chatEvalNothingLeft();
    File::deleteDirectory($dir);
});

test('an infrastructure error fails the command and still deletes the temporary user', function () {
    chatFake();
    chatEvalCatalog();
    // No such role: the temporary user is created, then assigning the role throws.
    Role::where('name', 'family_head')->delete();
    $dir = chatEvalDir();

    $this->artisan('ai:eval', ['suite' => 'chat', '--out-dir' => $dir])->assertFailed();

    expect(glob($dir.'/*'))->toBe([]);
    chatEvalNothingLeft();
    File::deleteDirectory($dir);
});

// ---- SYNTHETIC coverage of the golden cases the demo catalog cannot run (CHAT-04, CHAT-08) ----

test('SYNTHETIC CHAT-04: several rice variants make the server ask which one', function () {
    cfFood(['name' => 'Nasi putih matang']);
    cfFood(['name' => 'Nasi goreng']);
    $runner = new ChatEvalRunner(new ChatComparer);

    $item = $runner->runCase([
        'id' => 'CHAT-04', 'applicable' => true, 'turns' => [['message' => 'Berapa kalori nasi?']],
        'expect' => ['modes' => ['clarification'], 'retrieval_status' => 'ambiguous', 'model_calls' => 0, 'answer_contains' => ['Nasi goreng', 'Nasi putih matang']],
    ], true);
    $runner->cleanup();

    expect($item['pass'])->toBeTrue()->and($item['model_calls'])->toBe(0)->and($item['claims'])->toBe([]);
    chatEvalNothingLeft();
});

test('SYNTHETIC CHAT-08: a food revised between turns is answered with the newest revision', function () {
    $food = cfFood(['name' => 'Tahu contoh', 'protein_g' => '10', 'current_revision' => 3]);
    [$user, $session] = chatActor();
    $chat = app(ChatService::class);

    $first = $chat->send($user, $session->refresh(), ['message' => 'Berapa protein tahu contoh?', 'client_turn_id' => ctKey(1), 'food_id' => null, 'amount_g' => null], 'req_a');
    $food->update(['protein_g' => '12', 'current_revision' => 4]);
    $second = $chat->send($user, $session->refresh(), ['message' => 'kalau 50 gram?', 'client_turn_id' => ctKey(2), 'food_id' => null, 'amount_g' => null], 'req_b');

    expect($first['citations'][0]['revision'])->toBe(3)
        ->and($second['citations'][0]['revision'])->toBe(4)
        ->and(collect($second['claims'])->firstWhere('nutrient', 'protein_g')['derived_value'])->toBe(6)
        ->and(collect($second['claims'])->pluck('revision')->unique()->all())->toBe([4]);
});
