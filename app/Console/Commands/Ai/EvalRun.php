<?php

namespace App\Console\Commands\Ai;

use App\Exceptions\Ai\AiException;
use App\Services\Ai\Eval\RecordingGeminiClient;
use App\Services\Ai\Eval\ScanComparer;
use App\Services\Ai\Eval\ScanManifest;
use App\Services\Ai\Gemini\GeminiResult;
use App\Services\Ai\Scan\ImagePreparer;
use App\Services\Ai\Scan\LabelExtractor;
use App\Services\Ai\Scan\LabelNormalizer;
use App\Services\Ai\Scan\NutritionEvaluator;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * `ai:eval scan` runs every manifest image once through ImagePreparer + LabelExtractor (live)
 * and writes a sanitized result file. Named EvalRun because `Eval` is a reserved PHP word.
 * Results hold extracted numbers for public sample images only; never image bytes or base64.
 */
class EvalRun extends Command
{
    protected $signature = 'ai:eval {suite : scan (chat is added later)} {--split=all : dev, holdout or all} {--out-dir= : result directory (default ai/evals/results)}';

    protected $description = 'Run an AI evaluation suite live (one request per sample image) and save the result JSON.';

    public function handle(ImagePreparer $preparer, ScanComparer $comparer): int
    {
        if ($this->argument('suite') !== 'scan') {
            $this->error('Unknown suite. Only "scan" is available.');

            return self::INVALID;
        }
        $split = (string) $this->option('split');
        if (! in_array($split, ['dev', 'holdout', 'all'], true)) {
            $this->error('--split must be dev, holdout or all.');

            return self::INVALID;
        }
        if (config('ai.enabled') !== true || (string) config('ai.gemini.api_key', '') === '') {
            $this->error('AI is disabled or no provider key is configured; nothing was sent.');

            return self::FAILURE;
        }

        $manifest = base_path('ai/evals/ocr/manifest.csv');
        $rows = array_values(array_filter(ScanManifest::load($manifest), fn ($r) => $split === 'all' || $r['split'] === $split));
        $recorder = new RecordingGeminiClient;
        $extractor = new LabelExtractor($recorder, app(LabelNormalizer::class), app(NutritionEvaluator::class));

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->runOne($row, $preparer, $extractor, $recorder, $comparer);
            $this->line(sprintf('%s %-9s %s', $row['id'], $row['split'], $items[array_key_last($items)]['outcome']));
        }

        $summary = $comparer->summarize($items);
        $file = $this->write($split, $items, $summary);
        $this->table(['field', 'correct n/N', 'null ok', 'unsafe'], array_map(
            fn ($k, $f) => [$k, $f['correct'].'/'.$f['total'], $f['null_correct'].'/'.$f['null_total'], $f['unsafe']],
            array_keys($summary['fields']),
            $summary['fields'],
        ));
        $this->line(sprintf(
            'abstained %d (false %d), negative correct %d/%d, invalid output %d, errors %d, unsafe inferences %d',
            $summary['abstained'], $summary['false_abstentions'], $summary['negative_correct'], $summary['negative_total'],
            $summary['invalid_output'], $summary['errors'], $summary['unsafe_inferences'],
        ));
        $this->line('latency_ms '.json_encode($summary['latency_ms']).' usage '.json_encode($summary['usage']));
        $this->line('result: '.$file);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function runOne(array $row, ImagePreparer $preparer, LabelExtractor $extractor, RecordingGeminiClient $recorder, ScanComparer $comparer): array
    {
        $recorder->last = null;
        $response = null;
        $code = null;
        $start = hrtime(true);

        try {
            $file = new UploadedFile($row['path'], $row['file'], null, null, true);
            $response = $extractor->extract($preparer->prepare($file), 'req_eval_'.$row['id']);
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
            'condition' => $row['condition'],
            'extraction_status' => $response['extraction_status'] ?? null,
            'latency_ms' => (int) round((hrtime(true) - $start) / 1_000_000),
            'provider_latency_ms' => $result?->latencyMs,
            'model_version' => $result?->modelVersion,
            'usage' => $result?->usage,
        ] + $comparer->compare($row['truth'], $response, $code);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $summary
     */
    private function write(string $split, array $items, array $summary): string
    {
        $dir = (string) ($this->option('out-dir') ?: base_path('ai/evals/results'));
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir.'/scan-'.gmdate('Ymd\THis\Z').'.json';
        $versions = array_values(array_unique(array_filter(array_column($items, 'model_version'))));

        $payload = [
            'suite' => 'scan',
            'run_at_utc' => gmdate('c'),
            'split' => $split,
            'kind' => 'web_sample',
            'truth_status' => 'unverified_by_human',
            'caveat' => 'Mostly US/Canadian/European web samples with AI-transcribed, human-unverified truth. Not accuracy on Indonesian packaging.',
            'model_config' => (string) config('ai.gemini.model'),
            'model_versions_reported' => $versions,
            'prompt_sha256' => hash_file('sha256', base_path('ai/prompts/ocr-system.txt')),
            'requests' => count($items),
            'summary' => $summary,
            'items' => $items,
        ];
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");

        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}
