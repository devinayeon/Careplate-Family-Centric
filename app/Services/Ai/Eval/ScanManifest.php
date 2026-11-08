<?php

namespace App\Services\Ai\Eval;

use RuntimeException;

/**
 * Reads a scan eval manifest (default ai/evals/ocr/manifest.csv); images sit in an images/ folder next to it. Empty cells are null (not visible on the label), never 0.
 *
 * @phpstan-import-type Truth from ScanComparer
 *
 * @phpstan-type Row array{id: string, file: string, path: string, split: string, condition: string, sha256: string, kind: string, truth_status: string, truth: Truth}
 */
class ScanManifest
{
    private const NUTRIENT_COLUMNS = ScanComparer::NUTRIENTS;

    /**
     * @return list<Row>
     */
    public static function load(string $manifestPath): array
    {
        $handle = fopen($manifestPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Manifest not found.');
        }

        $header = fgetcsv($handle, null, ',', '"', '');
        $rows = [];
        while (($cells = fgetcsv($handle, null, ',', '"', '')) !== false) {
            if ($cells === [null] || $header === false || count($cells) !== count($header)) {
                continue;
            }
            $row = array_combine($header, $cells);
            $rows[] = [
                'id' => $row['id'],
                'file' => $row['file'],
                'path' => dirname($manifestPath).'/images/'.$row['file'],
                'split' => $row['split'],
                'condition' => $row['condition'],
                'sha256' => $row['sha256'],
                'kind' => $row['kind'],
                'truth_status' => $row['truth_status'],
                'truth' => self::truth($row),
            ];
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     * @return Truth
     */
    private static function truth(array $row): array
    {
        $number = fn (string $v): ?float => $v === '' ? null : (float) $v;
        $nutrients = [];
        foreach (self::NUTRIENT_COLUMNS as $key) {
            $nutrients[$key] = $number($row[$key]);
        }

        return [
            'expected_outcome' => $row['expected_outcome'],
            'label_basis' => $row['label_basis'] === '' ? null : $row['label_basis'],
            'serving_size' => [
                'value' => $number($row['serving_size_value']),
                'unit' => $row['serving_size_unit'] === '' ? null : $row['serving_size_unit'],
            ],
            'nutrients' => $nutrients,
            'less_than' => $row['less_than_fields'] === '' ? [] : explode(';', $row['less_than_fields']),
        ];
    }
}
