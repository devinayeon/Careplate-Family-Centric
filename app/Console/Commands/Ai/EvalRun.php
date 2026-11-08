<?php

namespace App\Console\Commands\Ai;

use App\Exceptions\Ai\AiException;
use App\Models\Food;
use App\Services\Ai\Eval\ChatComparer;
use App\Services\Ai\Eval\ChatEvalRunner;
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
 * and writes a sanitized result file. `ai:eval chat` runs the 12 golden chat cases through ChatService
 * (see ChatEvalRunner); only the general and mixed cases call the model. Named EvalRun because `Eval` is a reserved PHP word.
 * Results hold extracted numbers for public sample images only; never image bytes or base64.
 */
class EvalRun extends Command
{
    protected $signature = 'ai:eval {suite : scan or chat} {--split=all : dev, holdout or all} {--out-dir= : result directory (default ai/evals/results)}';

    protected $description = 'Run an AI evaluation suite live (one request per sample image) and save the result JSON.';

    public function handle(ImagePreparer $preparer, ScanComparer $comparer): int
    {
        if ($this->argument('suite') === 'chat') {
            return $this->chat();
        }
        if ($this->argument('suite') !== 'scan') {
            $this->error('Unknown suite. Use "scan" or "chat".');

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
        $path = $this->resultPath('scan');
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

    /**
     * Chat suite: infrastructure problems (database, missing role) fail the command; failed cases do not.
     * Without AI configured the model-free cases still run and the model cases are reported as skipped.
     */
    private function chat(): int
    {
        $enabled = config('ai.enabled') === true && (string) config('ai.gemini.api_key', '') !== '';
        if (! $enabled) {
            $this->warn('AI is disabled or no provider key is configured: cases that need the model are skipped.');
        }
        $comparer = app(ChatComparer::class);

        try {
            $cases = ChatEvalRunner::loadCases(base_path('ai/evals/chat-golden.jsonl'), base_path('ai/evals/chat-catalog-map.json'));
            $items = (new ChatEvalRunner($comparer))->run($cases, $enabled, fn (array $i) => $this->line(sprintf('%s %-14s %s', $i['id'], $i['outcome'], $i['actual_mode'] ?? '-')));
            $summary = $comparer->summarize($items);
            $file = $this->writeChat($items, $summary);
        } catch (Throwable $e) {
            $this->error('Chat eval infrastructure error: '.get_class($e));

            return self::FAILURE;
        }

        $this->table(['case', 'applicable', 'expected mode', 'actual mode', 'result', 'model calls', 'ms'], array_map(fn (array $i) => [
            $i['id'], $i['outcome'] === 'not_applicable' ? 'no' : ($i['skipped'] !== null ? 'skipped' : 'yes'),
            implode('|', $i['expected']['modes'] ?? []) ?: '-', $i['actual_mode'] ?? '-', $i['outcome'], $i['model_calls'], $i['latency_ms'] ?? '-',
        ], $items));
        $this->line(sprintf(
            'routing %s, passed %s, claims %s, citations %s, model-call expectation %s, model calls %d, unsafe claims %d, errors %d; not applicable %d, skipped %d',
            $summary['routing'], $summary['passed'], $summary['claims_ok'], $summary['citations_ok'], $summary['model_calls_ok'],
            $summary['model_calls'], $summary['unsafe_claims'], $summary['errors'], $summary['not_applicable'], $summary['skipped'],
        ));
        $this->line('latency_ms '.json_encode($summary['latency_ms']).' usage '.json_encode($summary['usage']));
        $this->line('result: '.$file);

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $summary
     */
    private function writeChat(array $items, array $summary): string
    {
        $path = $this->resultPath('chat');
        $versions = array_values(array_unique(array_filter(array_column($items, 'model_version'))));
        $payload = [
            'suite' => 'chat',
            'run_at_utc' => gmdate('c'),
            'kind' => 'demo_catalog_live',
            'truth_status' => 'rubric_from_spec_not_human_graded',
            'caveat' => 'One run per case against the 10-food USDA demo catalog. Cases whose premise the catalog lacks are listed as not applicable, not scored. Deterministic checks only (mode, status, claims, citations, model calls, unsafe numbers); relevance and honesty of model text still need human review (scores 0-2, spec 08). Not a statistic.',
            'model_config' => (string) config('ai.gemini.model'),
            'model_versions_reported' => $versions,
            'prompt_sha256' => hash_file('sha256', base_path('ai/prompts/chat-system.txt')),
            'golden_sha256' => hash_file('sha256', base_path('ai/evals/chat-golden.jsonl')),
            'catalog_map_sha256' => hash_file('sha256', base_path('ai/evals/chat-catalog-map.json')),
            'catalog' => Food::query()->verified()->orderBy('name')->get(['id', 'name', 'current_revision'])
                ->map(fn (Food $f) => ['id' => $f->id, 'name' => $f->name, 'revision' => (int) $f->current_revision])->all(),
            'model_requests' => $summary['model_calls'],
            'summary' => $summary,
            'items' => $items,
        ];
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");

        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }

    private function resultPath(string $suite): string
    {
        $dir = (string) ($this->option('out-dir') ?: base_path('ai/evals/results'));
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir.'/'.$suite.'-'.gmdate('Ymd\THis\Z').'.json';
    }
}
