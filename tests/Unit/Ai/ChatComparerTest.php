<?php

use App\Services\Ai\Eval\ChatComparer;

// SYNTHETIC expectations and responses only: no database, no model.

const CMP_FOOD = '11111111-1111-4111-8111-111111111111';

function cmpExpect(array $over = []): array
{
    return array_replace([
        'modes' => ['database'], 'retrieval_status' => 'found',
        'claims' => [['food_id' => CMP_FOOD, 'revision' => 3, 'nutrient' => 'protein_g', 'value' => '5']],
        'citations' => [['food_id' => CMP_FOOD, 'revision' => 3]],
        'model_calls' => 0, 'notice' => false, 'answer_contains' => ['protein'], 'answer_not_contains' => [],
    ], $over);
}

function cmpResponse(array $over = []): array
{
    return array_replace([
        'mode' => 'database', 'retrieval_status' => 'found', 'answer' => '50 g mengandung 5 g protein.',
        'claims' => [['food_id' => CMP_FOOD, 'revision' => 3, 'nutrient' => 'protein_g', 'derived_value' => 5.0]],
        'citations' => [['id' => 'food-1', 'food_id' => CMP_FOOD, 'revision' => 3]],
        'general_knowledge_notice' => null, 'warnings' => [],
    ], $over);
}

it('passes when routing, claims, citations and model calls all match', function () {
    $r = (new ChatComparer)->compare(cmpExpect(), cmpResponse(), null, 0, null);

    expect($r['pass'])->toBeTrue()->and($r['routing_ok'])->toBeTrue()->and($r['failures'])->toBe([])
        ->and($r['claims'])->toBe([['nutrient' => 'protein_g', 'value' => '5']]);
});

it('routes by mode list and retrieval status', function () {
    $c = new ChatComparer;
    $expect = cmpExpect(['modes' => ['general', 'clarification', 'referral'], 'retrieval_status' => null, 'claims' => [], 'citations' => [], 'answer_contains' => []]);

    $ok = $c->compare($expect, cmpResponse(['mode' => 'referral', 'retrieval_status' => 'not_needed', 'claims' => [], 'citations' => []]), null, 0, null);
    $wrong = $c->compare(cmpExpect(), cmpResponse(['mode' => 'general', 'retrieval_status' => 'no_match']), null, 0, null);
    $status = $c->compare(cmpExpect(), cmpResponse(['retrieval_status' => 'ambiguous']), null, 0, null);

    expect($ok['routing_ok'])->toBeTrue()
        ->and($wrong['routing_ok'])->toBeFalse()->and($wrong['pass'])->toBeFalse()
        ->and($status['mode_ok'])->toBeTrue()->and($status['routing_ok'])->toBeFalse();
});

it('compares claim values at two decimals with BCMath rounding', function () {
    $c = new ChatComparer;
    $expect = cmpExpect(['claims' => [['food_id' => CMP_FOOD, 'revision' => 3, 'nutrient' => 'protein_g', 'value' => '11.25']]]);
    $claim = fn ($v) => [['food_id' => CMP_FOOD, 'revision' => 3, 'nutrient' => 'protein_g', 'derived_value' => $v]];

    expect($c->compare($expect, cmpResponse(['claims' => $claim(11.25)]), null, 0, null)['claims_ok'])->toBeTrue()
        ->and($c->compare($expect, cmpResponse(['claims' => $claim(11.250004)]), null, 0, null)['claims_ok'])->toBeTrue()
        ->and($c->compare($expect, cmpResponse(['claims' => $claim(11.26)]), null, 0, null)['claims_ok'])->toBeFalse()
        ->and($c->compare($expect, cmpResponse(['claims' => []]), null, 0, null)['claims_ok'])->toBeFalse();
});

it('fails claims that cite another revision, extra nutrients or another food', function () {
    $c = new ChatComparer;
    $bad = fn (array $claim) => cmpResponse(['claims' => [array_replace(cmpResponse()['claims'][0], $claim)]]);

    expect($c->compare(cmpExpect(), $bad(['revision' => 4]), null, 0, null)['claims_ok'])->toBeFalse()
        ->and($c->compare(cmpExpect(), $bad(['food_id' => '22222222-2222-4222-8222-222222222222']), null, 0, null)['claims_ok'])->toBeFalse()
        ->and($c->compare(cmpExpect(['claims' => []]), cmpResponse(), null, 0, null)['claims_ok'])->toBeFalse();
});

it('checks citations as a set of food and revision', function () {
    $c = new ChatComparer;

    $stale = $c->compare(cmpExpect(), cmpResponse(['citations' => [['id' => 'food-1', 'food_id' => CMP_FOOD, 'revision' => 2]]]), null, 0, null);
    $none = $c->compare(cmpExpect(), cmpResponse(['citations' => []]), null, 0, null);

    expect($stale['citations_ok'])->toBeFalse()->and($none['citations_ok'])->toBeFalse()->and($none['pass'])->toBeFalse();
});

it('checks model calls exactly or as a maximum', function () {
    $c = new ChatComparer;

    expect($c->compare(cmpExpect(), cmpResponse(), null, 1, null)['model_calls_ok'])->toBeFalse()
        ->and($c->compare(cmpExpect(['model_calls' => 1]), cmpResponse(), null, 1, null)['model_calls_ok'])->toBeTrue()
        ->and($c->compare(cmpExpect(['model_calls' => null, 'model_calls_max' => 1]), cmpResponse(), null, 0, null)['model_calls_ok'])->toBeTrue()
        ->and($c->compare(cmpExpect(['model_calls' => null, 'model_calls_max' => 1]), cmpResponse(), null, 2, null)['model_calls_ok'])->toBeFalse();
});

it('checks the notice and required or forbidden answer words, ignoring case', function () {
    $c = new ChatComparer;

    expect($c->compare(cmpExpect(['notice' => true]), cmpResponse(), null, 0, null)['notice_ok'])->toBeFalse()
        ->and($c->compare(cmpExpect(['notice' => true]), cmpResponse(['general_knowledge_notice' => 'Umum.']), null, 0, null)['notice_ok'])->toBeTrue()
        ->and($c->compare(cmpExpect(['answer_contains' => ['PROTEIN']]), cmpResponse(), null, 0, null)['content_ok'])->toBeTrue()
        ->and($c->compare(cmpExpect(['answer_contains' => ['natrium']]), cmpResponse(), null, 0, null)['content_ok'])->toBeFalse()
        ->and($c->compare(cmpExpect(['answer_not_contains' => ['mengandung']]), cmpResponse(), null, 0, null)['content_ok'])->toBeFalse();
});

it('fails a case with an error code and reports it', function () {
    $r = (new ChatComparer)->compare(cmpExpect(), null, 'AI_TIMEOUT', 1, null);

    expect($r['pass'])->toBeFalse()->and($r['routing_ok'])->toBeFalse()->and($r['error_code'])->toBe('AI_TIMEOUT')
        ->and($r['actual_mode'])->toBeNull()->and($r['failures'])->toContain('error:AI_TIMEOUT');
});

it('counts digit-bearing nutrition numbers the server did not supply as unsafe claims', function () {
    $c = new ChatComparer;

    expect($c->unsafeClaims('Protein membantu membangun otot dan jaringan tubuh.'))->toBe(0)
        ->and($c->unsafeClaims('Dada ayam punya 31 g protein dan 165 kkal.'))->toBe(2)
        ->and($c->unsafeClaims('Kira-kira dua puluh gram protein.'))->toBe(1)
        ->and($c->unsafeClaims('Isinya 22,5 g protein.', ['22.5']))->toBe(0)
        ->and($c->unsafeClaims('Isinya 22,5 g protein dan 10 mg natrium.', ['22.5']))->toBe(1)
        ->and($c->unsafeClaims("Isinya \u{FF12}\u{FF10} g protein."))->toBe(1)
        ->and($c->unsafeClaims('Tidak ada angka.', []))->toBe(0);
});

it('reports unsafe claims of the narration on the case and fails the case', function () {
    $r = (new ChatComparer)->compare(cmpExpect(), cmpResponse(), null, 0, 'Sekitar 31 g protein.');

    expect($r['unsafe_claims'])->toBe(1)->and($r['pass'])->toBeFalse()->and($r['failures'])->toContain('unsafe_claims:1');
});

it('summarizes routing over applicable cases only and keeps not-applicable and skipped apart', function () {
    $item = fn (array $o) => $o + [
        'applicable' => true, 'skipped' => null, 'routing_ok' => true, 'pass' => true, 'claims_ok' => true, 'citations_ok' => true,
        'model_calls' => 0, 'model_calls_ok' => true, 'unsafe_claims' => 0, 'error_code' => null, 'latency_ms' => 10, 'usage' => null,
    ];
    $s = (new ChatComparer)->summarize([
        $item(['fidelity' => 'literal']),
        $item(['routing_ok' => false, 'pass' => false, 'latency_ms' => 30]),
        $item(['fidelity' => 'mapped_fixture', 'model_responses' => 1, 'narration_rejected' => true, 'rejected_unsafe_numbers' => 2, 'model_calls' => 1, 'latency_ms' => 3000, 'usage' => ['prompt' => 100, 'candidates' => 20, 'thoughts' => null, 'total' => 120]]),
        $item(['model_calls' => 1, 'latency_ms' => 5000, 'error_code' => 'AI_TIMEOUT', 'routing_ok' => false, 'pass' => false]),
        $item(['applicable' => false, 'routing_ok' => false, 'pass' => false]),
        $item(['skipped' => 'ai_disabled', 'routing_ok' => false, 'pass' => false]),
        $item(['unsafe_claims' => 2, 'pass' => false]),
    ]);

    expect($s['cases'])->toBe(7)->and($s['applicable'])->toBe(5)->and($s['not_applicable'])->toBe(1)->and($s['skipped'])->toBe(1)
        ->and($s['routing'])->toBe('3/5')->and($s['passed'])->toBe('2/5')
        ->and($s['unsafe_claims'])->toBe(2)->and($s['errors'])->toBe(1)->and($s['model_calls'])->toBe(2)
        ->and($s['latency_ms']['model'])->toBe(['n' => 2, 'p50' => 3000, 'p95' => 5000, 'max' => 5000])
        ->and($s['latency_ms']['server'])->toBe(['n' => 3, 'p50' => 10, 'p95' => 30, 'max' => 30])
        ->and($s['usage'])->toBe(['prompt' => 100, 'candidates' => 20, 'thoughts' => null, 'total' => 120])
        ->and($s['narrations_rejected'])->toBe('1/1')->and($s['rejected_unsafe_numbers'])->toBe(2)
        ->and($s['fidelity'])->toBe(['literal' => 1, 'mapped_fixture' => 1, 'substituted_nutrient' => 0, 'unspecified' => 3]);
});

it('does not count a number the server put into its own claims', function () {
    $r = (new ChatComparer)->compare(cmpExpect(), cmpResponse(['claims' => [cmpResponse()['claims'][0] + ['canonical_value' => 10, 'requested_amount' => 50]]]), null, 0, 'Takaran 50 g tetap perlu diperhatikan.');

    expect($r['unsafe_claims'])->toBe(0);
});
