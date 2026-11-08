<?php

use App\Models\Food;
use App\Models\FoodSubmission;
use App\Services\Ai\Foods\FoundationSnapshot;
use App\Services\Ai\Foods\SnapshotManifest;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

require_once __DIR__.'/FoundationFixture.php';

// SYNTHETIC archive bytes and Foundation JSON only. Http and the tar process are faked: no network.

const SYN_ZIP_URL = 'https://example.test/fdc/FoodData_Central_foundation_food_json_2099-12-31.zip';

function synDataDir(): string
{
    $dir = sys_get_temp_dir().'/ai-import-test-'.bin2hex(random_bytes(4));
    mkdir($dir);
    File::copy(base_path('ai/data/food-curation.csv'), $dir.'/food-curation.csv');

    return $dir;
}

function synFakeTar(string $dir): void
{
    // Plays the role of `tar -xf zip -C raw`: drops the synthetic Foundation JSON where tar would.
    Process::fake(function (PendingProcess $process) use ($dir) {
        file_put_contents($dir.'/raw/FoodData_Central_foundation_food_json_2099-12-31.json', json_encode(['FoundationFoods' => synFoods()]));

        return Process::result('', '', 0);
    });
}

function synCurationFile(string $dir): void
{
    $rows = array_map(fn ($r) => implode(',', [$r['candidate_id'], $r['priority'], '"'.$r['display_name_id'].'"', '"'.$r['search_query_en'].'"', '"'.$r['required_state'].'"']), synCuration());
    file_put_contents($dir.'/food-curation.csv', "candidate_id,priority,display_name_id,search_query_en,required_state\n".implode("\n", $rows)."\n");
}

it('downloads, extracts, imports and writes a manifest that answers source-triple questions', function () {
    $dir = synDataDir();
    synCurationFile($dir);
    Http::fake([SYN_ZIP_URL => Http::response('SYNTHETIC-ZIP-BYTES', 200)]);
    synFakeTar($dir);

    $this->artisan('ai:import-foods', ['--download' => true, '--url' => SYN_ZIP_URL, '--data-dir' => $dir])
        ->expectsOutputToContain('matched 7, skipped 2')
        ->assertSuccessful();

    $manifest = json_decode(file_get_contents($dir.'/snapshot.manifest.json'), true);
    $snapshot = $manifest['snapshots'][0];
    expect($snapshot)->toMatchArray([
        'source_provider' => 'USDA_FDC', 'snapshot_version' => '2099-12-31', 'url' => SYN_ZIP_URL,
        'sha256' => hash('sha256', 'SYNTHETIC-ZIP-BYTES'), 'record_count' => count(synFoods()),
    ])->and($snapshot['retrieved_at'])->not->toBeEmpty()
        ->and($snapshot['imported_source_ids'])->toBe(['900001', '900002', '900003', '900004', '900008', '900009', '900010']);

    $known = new SnapshotManifest($dir.'/snapshot.manifest.json');
    expect($known->sha256For('USDA_FDC', '900001', '2099-12-31'))->toBe(hash('sha256', 'SYNTHETIC-ZIP-BYTES'))
        ->and($known->sha256For('USDA_FDC', '900006', '2099-12-31'))->toBeNull()
        ->and($known->sha256For('USDA_FDC', '900001', '2000-01-01'))->toBeNull()
        ->and($known->sha256For('OTHER', '900001', '2099-12-31'))->toBeNull();
    expect(FoodSubmission::count())->toBe(7)->and(Food::count())->toBe(0);
    Http::assertSentCount(1);
    File::deleteDirectory($dir);
});

it('re-runs from the stored raw JSON without any download and reports unchanged', function () {
    $dir = synDataDir();
    synCurationFile($dir);
    Http::fake([SYN_ZIP_URL => Http::response('SYNTHETIC-ZIP-BYTES', 200)]);
    synFakeTar($dir);
    $options = ['--url' => SYN_ZIP_URL, '--data-dir' => $dir];
    $this->artisan('ai:import-foods', $options + ['--download' => true])->assertSuccessful();
    $manifestBefore = file_get_contents($dir.'/snapshot.manifest.json');

    $this->artisan('ai:import-foods', $options)
        ->expectsOutputToContain('created 0, unchanged 7, changed 0')
        ->assertSuccessful();

    Http::assertSentCount(1);
    expect(file_get_contents($dir.'/snapshot.manifest.json'))->toBe($manifestBefore)
        ->and(FoodSubmission::count())->toBe(7);
    File::deleteDirectory($dir);
});

it('fails clearly when extraction fails and writes nothing', function () {
    $dir = synDataDir();
    Http::fake([SYN_ZIP_URL => Http::response('SYNTHETIC-ZIP-BYTES', 200)]);
    Process::fake(['*' => Process::result('', 'bsdtar: bad archive', 1)]);

    $this->artisan('ai:import-foods', ['--download' => true, '--url' => SYN_ZIP_URL, '--data-dir' => $dir])
        ->expectsOutputToContain('Could not extract')
        ->assertFailed();

    expect(file_exists($dir.'/snapshot.manifest.json'))->toBeFalse()->and(FoodSubmission::count())->toBe(0);
    File::deleteDirectory($dir);
});

it('fails clearly on a download error and refuses to run without a stored snapshot', function () {
    $dir = synDataDir();
    Http::fake([SYN_ZIP_URL => Http::response('nope', 404)]);

    $this->artisan('ai:import-foods', ['--download' => true, '--url' => SYN_ZIP_URL, '--data-dir' => $dir])
        ->expectsOutputToContain('Download failed')->assertFailed();
    $this->artisan('ai:import-foods', ['--url' => SYN_ZIP_URL, '--data-dir' => $dir])
        ->expectsOutputToContain('--download')->assertFailed();

    expect(FoodSubmission::count())->toBe(0);
    File::deleteDirectory($dir);
});

it('refuses to overwrite a manifest entry when the same version has a different sha256', function () {
    $dir = synDataDir();
    synCurationFile($dir);
    Http::fake([SYN_ZIP_URL => Http::sequence()->push('FIRST-BYTES')->push('CHANGED-BYTES')]);
    synFakeTar($dir);
    $options = ['--download' => true, '--url' => SYN_ZIP_URL, '--data-dir' => $dir];
    $this->artisan('ai:import-foods', $options)->assertSuccessful();

    $this->artisan('ai:import-foods', $options)->expectsOutputToContain('different sha256')->assertFailed();

    $manifest = json_decode(file_get_contents($dir.'/snapshot.manifest.json'), true);
    expect($manifest['snapshots'][0]['sha256'])->toBe(hash('sha256', 'FIRST-BYTES'));
    File::deleteDirectory($dir);
});

it('derives the snapshot version from the archive name', function () {
    expect(FoundationSnapshot::versionFromUrl(SYN_ZIP_URL))->toBe('2099-12-31')
        ->and(fn () => FoundationSnapshot::versionFromUrl('https://example.test/random.zip'))->toThrow(RuntimeException::class);
});

it('only accepts the official https host for --url outside the testing environment', function () {
    $dir = synDataDir();
    Http::fake();
    app()->detectEnvironment(fn () => 'production');

    foreach (['https://evil.test/FoodData_Central_foundation_food_json_2099-12-31.zip', 'http://fdc.nal.usda.gov/FoodData_Central_foundation_food_json_2099-12-31.zip'] as $url) {
        $this->artisan('ai:import-foods', ['--download' => true, '--url' => $url, '--data-dir' => $dir])
            ->expectsOutputToContain('https://fdc.nal.usda.gov/')->assertExitCode(2);
    }

    Http::assertNothingSent();
    app()->detectEnvironment(fn () => 'testing');
    File::deleteDirectory($dir);
});

it('refreshes pending candidates through --refresh-pending and reports it', function () {
    $dir = synDataDir();
    synCurationFile($dir);
    Http::fake([SYN_ZIP_URL => Http::response('SYNTHETIC-ZIP-BYTES', 200)]);
    synFakeTar($dir);
    $options = ['--url' => SYN_ZIP_URL, '--data-dir' => $dir];
    $this->artisan('ai:import-foods', $options + ['--download' => true])->assertSuccessful();
    $submission = FoodSubmission::where('source_id', '900001')->firstOrFail();
    $stale = $submission->candidate;
    unset($stale['requires_review'], $stale['review_reasons']);
    $submission->update(['candidate' => $stale]);

    $this->artisan('ai:import-foods', $options)->expectsOutputToContain('changed 1, refreshed 0')->assertSuccessful();
    $this->artisan('ai:import-foods', $options + ['--refresh-pending' => true])->expectsOutputToContain('changed 0, refreshed 1')->assertSuccessful();

    expect($submission->fresh()->candidate)->toHaveKey('review_reasons');
    File::deleteDirectory($dir);
});
