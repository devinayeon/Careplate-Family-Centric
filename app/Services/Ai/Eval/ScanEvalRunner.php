<?php

namespace App\Services\Ai\Eval;

use App\Exceptions\Ai\AiException;
use App\Services\Ai\Gemini\GeminiResult;
use App\Services\Ai\Scan\ImagePreparer;
use App\Services\Ai\Scan\LabelExtractor;
use App\Services\Ai\Scan\LabelNormalizer;
use App\Services\Ai\Scan\NutritionEvaluator;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Runs manifest images through ImagePreparer + LabelExtractor (live, one request per image and run)
 * and compares each outcome with its truth. Items hold numbers and fixed server messages only,
 * never image bytes, base64, or provider text.
 *
 * @phpstan-import-type Row from ScanManifest
 */
class ScanEvalRunner
{
    public function __construct(private readonly ImagePreparer $preparer, private readonly ScanComparer $comparer) {}

    /**
     * @param  list<Row>  $rows
     * @param  callable(array<string, mixed>): void  $progress  called after every request
     * @return list<array<string, mixed>>
     */
    public function run(array $rows, int $runs, callable $progress): array
    {
        $recorder = new RecordingGeminiClient;
        $extractor = new LabelExtractor($recorder, app(LabelNormalizer::class), app(NutritionEvaluator::class));

        $items = [];
        for ($run = 1; $run <= $runs; $run++) {
            foreach ($rows as $row) {
                $items[] = $this->one($row, $run, $extractor, $recorder);
                $progress($items[array_key_last($items)]);
            }
        }

        return $items;
    }

    /**
     * @param  Row  $row
     * @return array<string, mixed>
     */
    private function one(array $row, int $run, LabelExtractor $extractor, RecordingGeminiClient $recorder): array
    {
        $recorder->last = null;
        $response = null;
        $code = null;
        $start = hrtime(true);

        try {
            $file = new UploadedFile($row['path'], $row['file'], null, null, true);
            $response = $extractor->extract($this->preparer->prepare($file), 'req_eval_'.$row['id'].'_'.$run);
        } catch (AiException $e) {
            $code = $e->errorCode;
        } catch (Throwable) {
            $code = 'INTERNAL';
        }

        /** @var GeminiResult|null $result */
        $result = $recorder->last;

        return [
            'id' => $row['id'],
            'file' => $row['file'],
            'split' => $row['split'],
            'kind' => $row['kind'],
            'run' => $run,
            'condition' => $row['condition'],
            'extraction_status' => $response['extraction_status'] ?? null,
            'needs_review' => $response['extraction']['needs_review'] ?? null,
            'warnings' => $response['warnings'] ?? [],
            'latency_ms' => (int) round((hrtime(true) - $start) / 1_000_000),
            'provider_latency_ms' => $result?->latencyMs,
            'model_version' => $result?->modelVersion,
            'usage' => $result?->usage,
        ] + $this->comparer->compare($row['truth'], $response, $code);
    }
}
