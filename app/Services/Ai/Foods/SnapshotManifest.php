<?php

namespace App\Services\Ai\Foods;

use RuntimeException;

/**
 * Committed record of the food source snapshots that were imported (ai/data/snapshot.manifest.json).
 * It answers "is this (provider, source id, snapshot version) a known imported record, and which
 * archive sha256 does it belong to". Entries only list ids the importer really matched.
 *
 * Entry fields: source_provider, snapshot_version, data_type, url, sha256, retrieved_at,
 * record_count, raw_json_file, imported_source_ids.
 */
class SnapshotManifest
{
    public const SCHEMA = 'careplate-snapshot-manifest-v1';

    public function __construct(private readonly string $path) {}

    /** @return list<array<string, mixed>> */
    public function snapshots(): array
    {
        if (! is_file($this->path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($this->path), true);

        return is_array($data) && is_array($data['snapshots'] ?? null) ? array_values($data['snapshots']) : [];
    }

    /** @return array<string, mixed>|null */
    public function find(string $provider, string $version): ?array
    {
        foreach ($this->snapshots() as $entry) {
            if (($entry['source_provider'] ?? null) === $provider && ($entry['snapshot_version'] ?? null) === $version) {
                return $entry;
            }
        }

        return null;
    }

    /** Archive sha256 when the triple was imported from that snapshot, otherwise null. */
    public function sha256For(string $provider, string $sourceId, string $version): ?string
    {
        $entry = $this->find($provider, $version);
        if ($entry === null || ! in_array($sourceId, $entry['imported_source_ids'] ?? [], true)) {
            return null;
        }

        return is_string($entry['sha256'] ?? null) ? $entry['sha256'] : null;
    }

    public function knows(string $provider, string $sourceId, string $version): bool
    {
        return $this->sha256For($provider, $sourceId, $version) !== null;
    }

    /** A snapshot version is frozen: the same version must never change its archive hash silently. */
    public function assertSha(string $provider, string $version, string $sha256): void
    {
        $existing = $this->find($provider, $version);
        if ($existing !== null && ($existing['sha256'] ?? null) !== $sha256) {
            throw new RuntimeException("Snapshot {$version} is already recorded with a different sha256; refusing to replace it.");
        }
    }

    /** @param  array<string, mixed>  $entry */
    public function record(array $entry): void
    {
        $provider = (string) $entry['source_provider'];
        $version = (string) $entry['snapshot_version'];
        $this->assertSha($provider, $version, (string) $entry['sha256']);

        $ids = array_map('strval', $entry['imported_source_ids'] ?? []);
        $existing = $this->find($provider, $version);
        if ($existing !== null) {
            $ids = array_merge($existing['imported_source_ids'] ?? [], $ids);
        }
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NATURAL);
        $entry['imported_source_ids'] = $ids;

        $snapshots = array_values(array_filter(
            $this->snapshots(),
            fn (array $e) => ! (($e['source_provider'] ?? null) === $provider && ($e['snapshot_version'] ?? null) === $version),
        ));
        $snapshots[] = $entry;
        usort($snapshots, fn (array $a, array $b) => [$a['source_provider'], $a['snapshot_version']] <=> [$b['source_provider'], $b['snapshot_version']]);

        $json = json_encode(
            ['schema_version' => self::SCHEMA, 'snapshots' => $snapshots],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        )."\n";
        if (is_file($this->path) && file_get_contents($this->path) === $json) {
            return;
        }
        if (! is_dir(dirname($this->path))) {
            mkdir(dirname($this->path), 0775, true);
        }
        file_put_contents($this->path, $json);
    }
}
