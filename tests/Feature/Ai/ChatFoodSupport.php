<?php

use App\Actions\Foods\FoodSearchText;
use App\Exceptions\Ai\AiException;
use App\Models\Food;

// SYNTHETIC foods only. Direct inserts are test setup; production rows come from ReviewFood.

/** Create a verified food row (search_text built like ReviewFood does). */
function cfFood(array $attrs = []): Food
{
    $name = $attrs['name'] ?? 'Tahu contoh';
    $aliases = $attrs['aliases'] ?? [mb_strtolower($name)];
    $description = $attrs['source_description'] ?? null;

    return Food::create(array_merge([
        'name' => $name, 'aliases' => $aliases, 'basis' => '100g',
        'energy_kcal' => '80', 'protein_g' => '10', 'fat_g' => '4.8', 'carbohydrate_g' => '1.9',
        'sodium_mg' => '7', 'sugars_total_g' => '0.5', 'energy_method' => null,
        'source_provider' => 'USDA_FDC', 'source_id' => (string) random_int(100000, 999999999),
        'source_url' => null, 'snapshot_version' => '2099-12-31', 'source_sha256' => null,
        'source_description' => $description, 'status' => 'verified', 'current_revision' => 3,
        'search_text' => FoodSearchText::build($name, $aliases, $description),
        'verified_at' => '2026-11-06 02:00:00',
    ], $attrs));
}

/** Names of the candidates, in order. */
function cfNames(iterable $foods): array
{
    return collect($foods)->map(fn (Food $f) => $f->name)->values()->all();
}

/** Runs $fn and returns the AiException it throws (fails the test when none). */
function cfThrown(Closure $fn): AiException
{
    try {
        $fn();
    } catch (AiException $e) {
        return $e;
    }
    throw new RuntimeException('Expected AiException, nothing thrown.');
}

/**
 * Structural check of a ChatResponse body against contracts/chat.schema.json (no JSON-schema library in the repo).
 *
 * @param  array<string, mixed>  $r  renderer output (ChatService adds the ids)
 */
function cfAssertChatShape(array $r): void
{
    $uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';
    expect(array_keys($r))->toEqualCanonicalizing(['mode', 'retrieval_status', 'answer', 'claims', 'citations', 'general_knowledge_notice', 'warnings'])
        ->and($r['mode'])->toBeIn(['database', 'general', 'mixed', 'clarification', 'referral'])
        ->and($r['retrieval_status'])->toBeIn(['found', 'no_match', 'ambiguous', 'not_needed'])
        ->and(mb_strlen($r['answer']))->toBeBetween(1, 6000)
        ->and(count($r['claims']))->toBeLessThanOrEqual(30)
        ->and(count($r['citations']))->toBeLessThanOrEqual(5)
        ->and(count($r['warnings']))->toBeLessThanOrEqual(20);
    foreach ($r['claims'] as $c) {
        expect(array_keys($c))->toEqualCanonicalizing(['food_id', 'revision', 'nutrient', 'canonical_value', 'unit', 'canonical_basis', 'requested_amount', 'derived_value', 'source_type', 'citation_id'])
            ->and($c['food_id'])->toMatch($uuid)
            ->and($c['nutrient'])->toBeIn(['energy_kcal', 'fat_g', 'sugars_total_g', 'protein_g', 'carbohydrate_g', 'sodium_mg'])
            ->and($c['unit'])->toBeIn(['kcal', 'g', 'mg'])
            ->and($c['canonical_basis'])->toBe('100g')
            ->and($c['source_type'])->toBe('database')
            ->and($c['canonical_value'])->toBeGreaterThanOrEqual(0)
            ->and($c['derived_value'])->toBeGreaterThanOrEqual(0)
            ->and($c['requested_amount'])->toBeGreaterThan(0)
            ->and(collect($r['citations'])->pluck('id')->all())->toContain($c['citation_id']);
    }
    foreach ($r['citations'] as $c) {
        expect(array_keys($c))->toEqualCanonicalizing(['id', 'food_id', 'revision', 'food_name', 'basis', 'source_reference', 'verified_at'])
            ->and($c['food_id'])->toMatch($uuid)
            ->and($c['revision'])->toBeGreaterThanOrEqual(1)
            ->and($c['basis'])->toBe('100g')
            ->and($c['source_reference'])->not->toBe('')
            ->and($c['verified_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/');
    }
    $mode = $r['mode'];
    if (in_array($mode, ['general', 'clarification', 'referral'], true)) {
        expect($r['claims'])->toBe([])->and($r['citations'])->toBe([]);
    }
    if (in_array($mode, ['database', 'mixed'], true)) {
        expect($r['citations'])->not->toBe([])->and($r['retrieval_status'])->toBe('found');
    }
    if (in_array($mode, ['general', 'mixed'], true)) {
        expect($r['general_knowledge_notice'])->toBeString()->not->toBe('')
            ->and(mb_strlen($r['general_knowledge_notice']))->toBeLessThanOrEqual(500);
    }
    if ($mode === 'clarification') {
        expect($r['retrieval_status'])->toBe('ambiguous');
    }
    if ($mode === 'general') {
        expect($r['retrieval_status'])->toBeIn(['no_match', 'not_needed']);
    }
}
