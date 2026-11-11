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

it('exposes kind and truth status per row and resolves images next to any manifest', function () {
    $real = ScanManifest::load(dirname(__DIR__, 3).'/ai/evals/ocr-id/manifest.csv');
    $synthetic = ScanManifest::load(dirname(__DIR__, 3).'/ai/evals/ocr-id/synthetic/manifest.csv');

    expect(array_unique(array_column($real, 'kind')))->toBe(['web_sample_id'])
        ->and(array_unique(array_column($real, 'truth_status')))->toBe(['unverified_by_human'])
        ->and(array_unique(array_column($synthetic, 'kind')))->toBe(['synthetic_id'])
        ->and(array_unique(array_column($synthetic, 'truth_status')))->toBe(['exact_by_construction'])
        ->and(array_unique(array_column(sampleManifest(), 'kind')))->toBe(['web_sample']);
});

it('keeps the Indonesian real set at 8 product-disjoint photos split 3 dev and 5 holdout', function () {
    $rows = ScanManifest::load(dirname(__DIR__, 3).'/ai/evals/ocr-id/manifest.csv');
    $handle = fopen(dirname(__DIR__, 3).'/ai/evals/ocr-id/manifest.csv', 'rb');
    $header = fgetcsv($handle, null, ',', '"', '');
    $products = [];
    while (($cells = fgetcsv($handle, null, ',', '"', '')) !== false) {
        $row = array_combine($header, $cells);
        expect($row['license'])->toContain('CC BY-SA 3.0')
            ->and($row['author_attribution'])->toContain('Open Food Facts contributor')
            ->and($row['source_url'])->toStartWith('https://world.openfoodfacts.org/product/')
            ->and($row['original_sha256'])->toMatch('/^[0-9a-f]{64}$/');
        preg_match('#/product/(\d+)#', $row['source_url'], $m);
        $products[] = $m[1];
    }
    fclose($handle);

    expect($rows)->toHaveCount(8)
        ->and(count(array_filter($rows, fn ($r) => $r['split'] === 'dev')))->toBe(3)
        ->and(array_unique($products))->toHaveCount(8);
    foreach ($rows as $row) {
        expect(hash_file('sha256', $row['path']))->toBe($row['sha256'])
            ->and(filesize($row['path']))->toBeLessThan(1_500_000);
    }
});

it('keeps the 6 synthetic labels exact by construction, with sodium unknown on the salt-only label', function () {
    $rows = ScanManifest::load(dirname(__DIR__, 3).'/ai/evals/ocr-id/synthetic/manifest.csv');
    $byId = array_column($rows, 'truth', 'id');

    expect($rows)->toHaveCount(6)
        ->and($byId['SYN-05']['nutrients']['sodium_mg'])->toBeNull()
        ->and($byId['SYN-05']['label_basis'])->toBe('per_100g')
        ->and($byId['SYN-04']['less_than'])->toBe(['sugars_total_g'])
        ->and($byId['SYN-04']['nutrients']['protein_g'])->toBe(0.0)
        ->and($byId['SYN-06']['serving_size'])->toBe(['value' => null, 'unit' => null]);
    foreach ($rows as $row) {
        expect(hash_file('sha256', $row['path']))->toBe($row['sha256']);
    }
});

it('does not claim a download date for generated labels', function () {
    $handle = fopen(dirname(__DIR__, 3).'/ai/evals/ocr-id/synthetic/manifest.csv', 'rb');
    $header = fgetcsv($handle, null, ',', '"', '');
    $count = 0;
    while (($cells = fgetcsv($handle, null, ',', '"', '')) !== false) {
        $row = array_combine($header, $cells);
        expect($row['downloaded_at'])->toBe('')->and($row['source_url'])->toStartWith('generated:');
        $count++;
    }
    fclose($handle);

    expect($count)->toBe(6);
});

it('rejects a manifest file name that is not a plain file name in the images folder', function (string $file) {
    $dir = sys_get_temp_dir().'/ai-manifest-'.bin2hex(random_bytes(4));
    mkdir($dir);
    $header = 'id,file,split,condition,expected_outcome,label_basis,serving_size_value,serving_size_unit,energy_kcal,fat_g,sugars_total_g,protein_g,carbohydrate_g,sodium_mg,less_than_fields,sha256,kind,truth_status';
    file_put_contents($dir.'/manifest.csv', $header."\nX-01,\"{$file}\",dev,clear,extract,,,,,,,,,,,abc,web_sample,unverified_by_human\n");

    try {
        expect(fn () => ScanManifest::load($dir.'/manifest.csv'))->toThrow(RuntimeException::class);
    } finally {
        unlink($dir.'/manifest.csv');
        rmdir($dir);
    }
})->with(['../secret.jpg', 'sub/dir.jpg', 'sub\dir.jpg', '..', '/etc/passwd', 'C:\x.jpg']);

it('accepts a plain image file name', function () {
    $dir = sys_get_temp_dir().'/ai-manifest-'.bin2hex(random_bytes(4));
    mkdir($dir);
    $header = 'id,file,split,condition,expected_outcome,label_basis,serving_size_value,serving_size_unit,energy_kcal,fat_g,sugars_total_g,protein_g,carbohydrate_g,sodium_mg,less_than_fields,sha256,kind,truth_status';
    file_put_contents($dir.'/manifest.csv', $header."\nX-01,plain-1.jpg,dev,clear,extract,,,,,,,,,,,abc,web_sample,unverified_by_human\n");

    try {
        $rows = ScanManifest::load($dir.'/manifest.csv');
        expect($rows[0]['file'])->toBe('plain-1.jpg')->and($rows[0]['path'])->toBe($dir.'/images/plain-1.jpg');
    } finally {
        unlink($dir.'/manifest.csv');
        rmdir($dir);
    }
});
