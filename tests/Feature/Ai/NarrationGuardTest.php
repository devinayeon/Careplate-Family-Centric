<?php

use App\Models\ChatTurn;
use App\Services\Ai\Chat\MemoryBuilder;
use App\Services\Ai\Chat\NarrationGuard;

require_once __DIR__.'/ChatTurnSupport.php';

test('plain education passes the guard', function (string $text) {
    expect((new NarrationGuard)->safe($text))->toBeTrue();
})->with([
    'label advice' => 'Perhatikan takaran saji saat membandingkan label. Ini edukasi umum.',
    'omega-3' => 'Omega-3 dan vitamin B12 sering dibahas dalam edukasi gizi.',
    'informal nih' => 'Ini penting nih untuk dipahami.',
    'count without unit' => 'Makan sayur setiap hari dan minum cukup air.',
    'sentence end then new word' => 'Baca label dulu.Lalu bandingkan.',
]);

test('nutrition numbers, links, citation markers and unnamed sources are blocked', function (string $text) {
    expect((new NarrationGuard)->safe($text))->toBeFalse();
})->with([
    'g' => '10 g protein',
    'no space mg' => 'natrium 450mg',
    'kcal' => '2.000 kcal sehari',
    'kj' => '300 kJ',
    'percent' => 'sekitar 15%',
    'percent word' => 'dua puluh persen',
    'full width digits' => '５０ gram',
    'mixed case unit' => '50 GRAM gula',
    'spelled' => 'seratus gram nasi',
    'http' => 'lihat http://x.test',
    'https' => 'lihat HTTPS://x.test',
    'www' => 'kunjungi www.contoh.id',
    'markdown' => '[di sini](x)',
    'tld' => 'cek kemkes.go.id',
    'bracket' => 'penting [2]',
    'sumber' => 'Sumber: penelitian terbaru',
    'who' => 'Menurut WHO, serat penting.',
    'pubmed' => 'Dimuat di PubMed.',
]);

test('a source name is allowed when the server supplied it', function () {
    $guard = new NarrationGuard;

    expect($guard->safe('Data USDA ditinjau sumber.', 'USDA FoodData Central, ID 1'))->toBeTrue()
        ->and($guard->safe('Menurut WHO.', 'USDA FoodData Central, ID 1'))->toBeFalse();
});

test('output() accepts exactly the provider-chat shape', function () {
    $guard = new NarrationGuard;
    $schema = json_decode(file_get_contents(base_path('ai/schemas/provider-chat.json')), true);

    expect($schema['properties']['kind']['enum'])->toBe(NarrationGuard::KINDS)
        ->and($schema['required'])->toBe(['kind', 'text'])->and($schema['additionalProperties'])->toBeFalse()
        ->and($guard->output(['kind' => 'educational', 'text' => ' Halo. ']))->toBe(['kind' => 'educational', 'text' => 'Halo.'])
        ->and($guard->output(['text' => 'Halo.', 'kind' => 'referral']))->toBe(['kind' => 'referral', 'text' => 'Halo.'])
        ->and($guard->output(['kind' => 'educational', 'text' => 'x', 'extra' => 1]))->toBeNull()
        ->and($guard->output(['kind' => 'educational']))->toBeNull()
        ->and($guard->output(['kind' => 'other', 'text' => 'x']))->toBeNull()
        ->and($guard->output(['kind' => 'educational', 'text' => 5]))->toBeNull()
        ->and($guard->output(['kind' => 'educational', 'text' => str_repeat('a', 2001)]))->toBeNull()
        ->and($guard->output(['kind' => 'educational', 'text' => str_repeat('a', 2000)]))->not->toBeNull();
});

test('memory keeps completed turns of one session only, newest window, oldest first', function () {
    $session = ctSession(ctUser(''));
    $other = ctSession(ctUser(''));
    foreach (range(1, 8) as $n) {
        ctTurn($session, $n, ['message' => "Tanya $n", 'response' => ctResponse('general')]);
    }
    ctTurn($session, 9, ['status' => 'failed', 'message' => 'gagal', 'response' => null]);
    ctTurn($session, 10, ['status' => 'pending', 'message' => 'menunggu', 'response' => null]);
    ctTurn($other, 1, ['message' => 'sesi lain']);
    ChatTurn::query()->where('session_id', $session->id)->where('sequence', 1)->update(['created_at' => now()->subDays(30)]);

    $pairs = (new MemoryBuilder)->history($session, 'Apa kabar?', 'katalog');

    expect(array_column($pairs, 'user'))->toBe(['Tanya 3', 'Tanya 4', 'Tanya 5', 'Tanya 6', 'Tanya 7', 'Tanya 8']);
});

test('memory drops the oldest pairs while the estimate exceeds the budget, and redacts numbers', function () {
    $session = ctSession(ctUser(''));
    foreach (range(1, 4) as $n) {
        ctTurn($session, $n, ['message' => str_repeat('a', 30), 'response' => ['answer' => 'Hasil 12,5 g dan 300 kcal'] + ctResponse()]);
    }
    $builder = new MemoryBuilder;

    $all = $builder->history($session, 'q', 'f');
    $pairLength = mb_strlen($all[0]['user']) + mb_strlen($all[0]['assistant']);
    config(['ai.context.text_token_budget' => (int) ceil((2 * $pairLength + 2) / 3)]);
    $cut = $builder->history($session, 'q', 'f');
    config(['ai.context.text_token_budget' => 1]);
    $none = $builder->history($session, 'q', 'f');

    expect($all)->toHaveCount(4)->and($all[0]['assistant'])->toBe('Hasil [angka] g dan [angka] kcal')
        ->and($cut)->toHaveCount(2)->and($none)->toBe([])
        ->and(MemoryBuilder::estimate([], str_repeat('a', 7), ''))->toBe(3);
});

test('the carried food is the sole food of the last completed turn only', function () {
    $session = ctSession(ctUser(''));
    $builder = new MemoryBuilder;
    $a = ['food_id' => '10000000-0000-4000-8000-000000000001', 'revision' => 3];
    $b = ['food_id' => '10000000-0000-4000-8000-000000000002', 'revision' => 1];

    expect($builder->carriedFoodId($session))->toBeNull();
    ctTurn($session, 1, ['food_refs' => [$a]]);
    expect($builder->carriedFoodId($session))->toBe($a['food_id']);
    ctTurn($session, 2, ['food_refs' => [$a, $b]]);
    expect($builder->carriedFoodId($session))->toBeNull();
    ctTurn($session, 3, ['food_refs' => [$b, $b]]);
    expect($builder->carriedFoodId($session))->toBe($b['food_id']);
    ctTurn($session, 4, ['status' => 'failed', 'food_refs' => null, 'response' => null]);
    expect($builder->carriedFoodId($session))->toBe($b['food_id']);
});
