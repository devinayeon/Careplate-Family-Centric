<?php

use App\Exceptions\Ai\AiException;
use App\Models\FoodSubmission;
use App\Services\Ai\Foods\SnapshotManifest;

// SYNTHETIC source ids and numbers only; the manifest here is a temp file bound into the container.

const RF_SHA = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

/** Writes a temp manifest listing $ids for USDA_FDC 2099-12-31 and binds it for ReviewFood. */
function rfManifest(array $ids = ['900001', '900002', '900003'], string $sha = RF_SHA): SnapshotManifest
{
    $path = sys_get_temp_dir().'/rf-manifest-'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($path, json_encode([
        'schema_version' => SnapshotManifest::SCHEMA,
        'snapshots' => [[
            'source_provider' => 'USDA_FDC', 'snapshot_version' => '2099-12-31', 'data_type' => 'Foundation',
            'url' => 'https://example.test/x.zip', 'sha256' => $sha, 'retrieved_at' => '2099-12-31T00:00:00+00:00',
            'record_count' => 3, 'raw_json_file' => 'x.json', 'imported_source_ids' => $ids,
        ]],
    ]));
    $manifest = new SnapshotManifest($path);
    app()->instance(SnapshotManifest::class, $manifest);

    return $manifest;
}

/** @return array<string, mixed> */
function rfCandidate(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Beras putih mentah',
        'aliases' => ['beras putih mentah', 'beras putih'],
        'basis' => '100g',
        'nutrients' => [
            'energy_kcal' => '370', 'protein_g' => '7.1', 'fat_g' => '0.7', 'carbohydrate_g' => '80',
            'sodium_mg' => '1', 'sugars_total_g' => null, 'energy_method' => 'Atwater specific',
        ],
        'source_description' => 'Rice, white, long grain, unenriched, raw',
        'provenance' => ['source' => ['provider' => 'USDA_FDC', 'fdc_id' => '900001', 'sha256' => RF_SHA]],
        'requires_review' => false,
        'review_reasons' => [],
    ], $overrides);
}

function rfSubmission(array $attrs = [], array $candidate = []): FoodSubmission
{
    return FoodSubmission::create(array_merge([
        'status' => 'pending',
        'revision_counter' => 2,
        'candidate' => rfCandidate($candidate),
        'source_provider' => 'USDA_FDC',
        'source_id' => '900001',
        'source_url' => 'https://example.test/food/900001',
        'snapshot_version' => '2099-12-31',
        'source_sha256' => RF_SHA,
    ], $attrs));
}

/** Runs $fn and returns the AiException it throws (fails the test when none). */
function rfThrown(Closure $fn): AiException
{
    try {
        $fn();
    } catch (AiException $e) {
        return $e;
    }
    throw new RuntimeException('Expected AiException, nothing thrown.');
}
