<?php

namespace App\Services\Ai\Foods;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Fetches the Foundation Foods JSON archive into the git-ignored raw directory, hashes it and
 * unpacks it. PHP has no zip extension here, so extraction uses `tar -xf` (bsdtar reads zip).
 */
class FoundationSnapshot
{
    public const DEFAULT_URL = 'https://fdc.nal.usda.gov/fdc-datasets/FoodData_Central_foundation_food_json_2026-10-29.zip';

    public function __construct(private readonly string $rawDir) {}

    public static function versionFromUrl(string $url): string
    {
        if (preg_match('/(\d{4}-\d{2}-\d{2})\.zip$/', (string) parse_url($url, PHP_URL_PATH), $m) !== 1) {
            throw new RuntimeException('The archive URL must end with a date like 2026-10-29.zip so the snapshot version is known.');
        }

        return $m[1];
    }

    public function zipPath(string $url): string
    {
        return $this->rawDir.'/'.basename((string) parse_url($url, PHP_URL_PATH));
    }

    public function jsonPath(string $url): string
    {
        return preg_replace('/\.zip$/', '.json', $this->zipPath($url)) ?? $this->zipPath($url);
    }

    /** Downloads to a .part file and returns its sha256. Call discard() or extract() afterwards. */
    public function fetch(string $url): string
    {
        $part = $this->zipPath($url).'.part';
        if (! is_dir($this->rawDir)) {
            mkdir($this->rawDir, 0775, true);
        }
        try {
            $response = Http::timeout(180)->withOptions(['sink' => $part])->get($url);
        } catch (ConnectionException $e) {
            $this->discard($url);
            throw new RuntimeException('Download failed: could not reach the server.');
        }
        if ($response->failed()) {
            $this->discard($url);
            throw new RuntimeException('Download failed: HTTP '.$response->status().' for '.$url);
        }

        return hash_file('sha256', $part);
    }

    public function discard(string $url): void
    {
        @unlink($this->zipPath($url).'.part');
    }

    /** Promotes the downloaded .part to the zip and extracts it; returns the JSON path. */
    public function extract(string $url): string
    {
        $zip = $this->zipPath($url);
        rename($zip.'.part', $zip);

        $windowsTar = (string) getenv('SystemRoot').'\\System32\\tar.exe';
        $tar = PHP_OS_FAMILY === 'Windows' && is_file($windowsTar) ? $windowsTar : 'tar';
        $result = Process::timeout(120)->run([$tar, '-xf', $zip, '-C', $this->rawDir]);
        if ($result->failed()) {
            throw new RuntimeException('Could not extract '.basename($zip).' with tar: '.trim($result->errorOutput() ?: 'exit code '.$result->exitCode()));
        }
        if (! is_file($this->jsonPath($url))) {
            throw new RuntimeException('Could not extract: '.basename($this->jsonPath($url)).' was not found in the archive.');
        }

        return $this->jsonPath($url);
    }

    /** @return list<array<string, mixed>> */
    public function loadFoods(string $jsonPath): array
    {
        $data = json_decode((string) file_get_contents($jsonPath), true);
        if (! is_array($data) || ! is_array($data['FoundationFoods'] ?? null)) {
            throw new RuntimeException('The snapshot JSON has no FoundationFoods list.');
        }

        return array_values($data['FoundationFoods']);
    }
}
