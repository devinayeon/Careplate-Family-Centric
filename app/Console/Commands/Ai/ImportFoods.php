<?php

namespace App\Console\Commands\Ai;

use App\Services\Ai\Foods\FoundationSnapshot;
use App\Services\Ai\Foods\SnapshotManifest;
use App\Services\Ai\Foods\UsdaFoundationImporter;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Imports USDA Foundation Foods records for the curation list as PENDING food_submissions and
 * records the snapshot in ai/data/snapshot.manifest.json. It never creates foods or approves.
 */
class ImportFoods extends Command
{
    protected $signature = 'ai:import-foods {--download : Download and extract the snapshot archive first} {--limit=20 : Curation rows to process, by priority} {--url= : Archive URL (default: the Foundation Foods archive)} {--data-dir= : Data directory (default: ai/data)}';

    protected $description = 'Import USDA Foundation Foods records for the curation list as pending food submissions.';

    private const PROVIDER = 'USDA_FDC';

    public function handle(UsdaFoundationImporter $importer): int
    {
        $limit = (int) $this->option('limit');
        if ($limit < 1 || $limit > 100) {
            $this->error('--limit must be between 1 and 100.');

            return self::INVALID;
        }
        $dir = rtrim((string) ($this->option('data-dir') ?: base_path('ai/data')), '/\\');
        $url = (string) ($this->option('url') ?: FoundationSnapshot::DEFAULT_URL);
        $source = new FoundationSnapshot($dir.'/raw');
        $manifest = new SnapshotManifest($dir.'/snapshot.manifest.json');

        try {
            $version = FoundationSnapshot::versionFromUrl($url);
            if ($this->option('download')) {
                [$json, $meta] = $this->download($source, $manifest, $url, $version);
            } else {
                [$json, $meta] = $this->stored($source, $manifest, $url, $version);
            }
            $foods = $source->loadFoods($json);
            $report = $importer->import(
                $this->curation($dir.'/food-curation.csv'),
                $foods,
                ['provider' => self::PROVIDER, 'version' => $version, 'sha256' => $meta['sha256'], 'url' => $meta['url']],
                $limit,
            );
            $manifest->record([
                'source_provider' => self::PROVIDER, 'snapshot_version' => $version, 'data_type' => 'Foundation',
                'url' => $meta['url'], 'sha256' => $meta['sha256'], 'retrieved_at' => $meta['retrieved_at'],
                'record_count' => count($foods), 'raw_json_file' => basename($json),
                'imported_source_ids' => $report['source_ids'],
            ]);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($report['rows'] as $row) {
            $this->line($this->describe($row));
        }
        $c = $report['counts'];
        $this->line("snapshot {$version} sha256 {$meta['sha256']}");
        $this->line("matched {$c['matched']}, skipped {$c['skipped']}; created {$c['created']}, unchanged {$c['unchanged']}, changed {$c['changed']}");

        return self::SUCCESS;
    }

    /** @return array{0: string, 1: array{url: string, sha256: string, retrieved_at: string}} */
    private function download(FoundationSnapshot $source, SnapshotManifest $manifest, string $url, string $version): array
    {
        $sha = $source->fetch($url);
        try {
            $manifest->assertSha(self::PROVIDER, $version, $sha);
        } catch (RuntimeException $e) {
            $source->discard($url);
            throw new RuntimeException($e->getMessage());
        }

        return [$source->extract($url), ['url' => $url, 'sha256' => $sha, 'retrieved_at' => now()->toIso8601String()]];
    }

    /** @return array{0: string, 1: array{url: string, sha256: string, retrieved_at: string}} */
    private function stored(FoundationSnapshot $source, SnapshotManifest $manifest, string $url, string $version): array
    {
        $entry = $manifest->find(self::PROVIDER, $version);
        $json = $source->jsonPath($url);
        if ($entry === null || ! is_file($json)) {
            throw new RuntimeException("No stored snapshot {$version}; run with --download first.");
        }

        return [$json, ['url' => (string) $entry['url'], 'sha256' => (string) $entry['sha256'], 'retrieved_at' => (string) $entry['retrieved_at']]];
    }

    /** @return list<array<string, string>> */
    private function curation(string $path): array
    {
        $handle = is_file($path) ? fopen($path, 'r') : false;
        if ($handle === false) {
            throw new RuntimeException("Curation list not found: {$path}");
        }
        $header = fgetcsv($handle, escape: '');
        $rows = [];
        while (($line = fgetcsv($handle, escape: '')) !== false) {
            if (is_array($header) && count($line) === count($header)) {
                $rows[] = array_combine($header, $line);
            }
        }
        fclose($handle);

        return $rows;
    }

    /** @param  array<string, mixed>  $row */
    private function describe(array $row): string
    {
        if ($row['status'] === 'skipped') {
            return sprintf('%s skipped: %s', $row['candidate_id'], $row['reason']);
        }
        $changed = isset($row['changed']) ? ' ('.implode(', ', $row['changed']).')' : '';

        return sprintf('%s %s -> %s %s%s', $row['candidate_id'], $row['status'], $row['fdc_id'], $row['description'], $changed);
    }
}
