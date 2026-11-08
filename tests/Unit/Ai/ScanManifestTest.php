<?php

use App\Services\Ai\Eval\ScanManifest;

function sampleManifest(): array
{
    return ScanManifest::load(dirname(__DIR__, 3).'/ai/evals/ocr/manifest.csv');
}

it('lists 8 sample images split 3 dev and 5 holdout with the planned mix', function () {
    $rows = sampleManifest();
    $count = fn (string $col, string $v) => count(array_filter($rows, fn ($r) => $r[$col] === $v));

    expect($rows)->toHaveCount(8)
        ->and($count('split', 'dev'))->toBe(3)
        ->and($count('split', 'holdout'))->toBe(5)
        ->and($count('condition', 'clear'))->toBeGreaterThanOrEqual(4)
        ->and($count('condition', 'difficult'))->toBe(1)
        ->and($count('condition', 'partial'))->toBeGreaterThanOrEqual(1)
        ->and($count('condition', 'negative'))->toBe(1);
});

it('points at real image files whose bytes match the recorded sha256', function () {
    foreach (sampleManifest() as $row) {
        expect(is_file($row['path']))->toBeTrue($row['file'])
            ->and(hash_file('sha256', $row['path']))->toBe($row['sha256'])
            ->and(filesize($row['path']))->toBeLessThan(1_500_000)
            ->and((new finfo(FILEINFO_MIME_TYPE))->file($row['path']))->toBeIn(['image/jpeg', 'image/png']);
    }
});

it('carries licence, attribution, truth status and kind for every row', function () {
    $handle = fopen(dirname(__DIR__, 3).'/ai/evals/ocr/manifest.csv', 'rb');
    $header = fgetcsv($handle, null, ',', '"', '');
    while (($cells = fgetcsv($handle, null, ',', '"', '')) !== false) {
        $row = array_combine($header, $cells);
        expect($row['license'])->not->toBe('')
            ->and($row['author_attribution'])->not->toBe('')
            ->and($row['source_url'])->toStartWith('https://commons.wikimedia.org/wiki/File:')
            ->and($row['downloaded_at'])->toBe('2026-11-08')
            ->and($row['truth_status'])->toBe('unverified_by_human')
            ->and($row['kind'])->toBe('web_sample')
            ->and($row['original_sha256'])->toMatch('/^[0-9a-f]{64}$/');
    }
    fclose($handle);
});

it('keeps unknown truth as null and exact zero as 0.0', function () {
    $byId = array_column(sampleManifest(), 'truth', 'id');

    expect($byId['SMP-01']['nutrients']['sodium_mg'])->toBeNull()
        ->and($byId['SMP-01']['nutrients']['fat_g'])->toBe(0.0)
        ->and($byId['SMP-07']['nutrients']['energy_kcal'])->toBeNull()
        ->and($byId['SMP-05']['nutrients']['sodium_mg'])->toBe(0.0)
        ->and($byId['SMP-03']['expected_outcome'])->toBe('unreadable')
        ->and($byId['SMP-03']['serving_size'])->toBe(['value' => null, 'unit' => null]);
});
