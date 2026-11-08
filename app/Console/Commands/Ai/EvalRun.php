<?php

namespace App\Console\Commands\Ai;

use App\Models\Food;
use App\Services\Ai\Eval\ChatComparer;
use App\Services\Ai\Eval\ChatEvalRunner;
use App\Services\Ai\Eval\ScanComparer;
use App\Services\Ai\Eval\ScanEvalRunner;
use App\Services\Ai\Eval\ScanManifest;
use App\Services\Ai\Eval\ScanRunStats;
use App\Services\Ai\Scan\ImagePreparer;
use Illuminate\Console\Command;
use Throwable;

/**
 * `ai:eval scan` runs every image of a manifest `--runs` times through ImagePreparer + LabelExtractor (live)
 * and writes a sanitized result file (see ScanEvalRunner, ScanRunStats). `ai:eval chat` runs the 12 golden chat cases through ChatService
 * (see ChatEvalRunner); only the general and mixed cases call the model. Named EvalRun because `Eval` is a reserved PHP word.
 * Results hold extracted numbers for public sample images only; never image bytes or base64.
 */
class EvalRun extends Command
{
    protected $signature = 'ai:eval {suite : scan or chat} {--split=all : dev, holdout or all} {--manifest=ai/evals/ocr/manifest.csv : scan manifest under ai/evals} {--runs=1 : repeat every scan image N times (1 to 5)} {--pause=0 : seconds to wait between scan requests (0 to 60)} {--rescore= : recompute the summary of a saved scan result file offline (no request is sent)} {--out-dir= : result directory (default ai/evals/results)}';

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
        if ((string) $this->option('rescore') !== '') {
            return $this->rescore((string) $this->option('rescore'), $comparer);
        }
        $split = (string) $this->option('split');
        if (! in_array($split, ['dev', 'holdout', 'all'], true)) {
            $this->error('--split must be dev, holdout or all.');

            return self::INVALID;
        }
        $runs = filter_var($this->option('runs'), FILTER_VALIDATE_INT);
        if ($runs === false || $runs < 1 || $runs > 5) {
            $this->error('--runs must be a whole number from 1 to 5.');

            return self::INVALID;
        }
        $pause = filter_var($this->option('pause'), FILTER_VALIDATE_INT);
        if ($pause === false || $pause < 0 || $pause > 60) {
            $this->error('--pause must be a whole number of seconds from 0 to 60.');

            return self::INVALID;
        }
        $manifest = $this->manifestPath((string) $this->option('manifest'));
        if ($manifest === null) {
            $this->error('--manifest must be an existing .csv file under ai/evals.');

            return self::INVALID;
        }
        if (config('ai.enabled') !== true || (string) config('ai.gemini.api_key', '') === '') {
            $this->error('AI is disabled or no provider key is configured; nothing was sent.');

            return self::FAILURE;
        }

        $rows = array_values(array_filter(ScanManifest::load($manifest), fn ($r) => $split === 'all' || $r['split'] === $split));
        $this->line(sprintf('%d images x %d runs = %d requests', count($rows), $runs, count($rows) * $runs));
        $items = (new ScanEvalRunner($preparer, $comparer))->run($rows, $runs, fn (array $i) => $this->line(
            sprintf('%s run %d %-9s %s', $i['id'], $i['run'], $i['split'], $i['outcome']),
        ), $pause);

        $stats = (new ScanRunStats($comparer))->build($items);
        $file = $this->write($split, $runs, $manifest, $rows, $items, $stats);
        $this->report($stats);
        $this->line('result: '.$file);

        return self::SUCCESS;
    }

    /** Only CSV manifests under ai/evals are accepted, so no other image can be sent to the provider. */
    private function manifestPath(string $option): ?string
    {
        $absolute = preg_match('#^([A-Za-z]:)?[\\\\/]#', $option) === 1;
        $path = realpath($absolute ? $option : base_path($option));
        $root = realpath(base_path('ai/evals'));

        return $path !== false && $root !== false && is_file($path) && str_ends_with($path, '.csv')
            && str_starts_with($path, $root.DIRECTORY_SEPARATOR) ? $path : null;
    }

    /**
     * Recomputes summary and per-image aggregates of a saved result from its stored items; the items stay as they are.
     * No provider request is made, so this works without a key.
     */
    private function rescore(string $option, ScanComparer $comparer): int
    {
        $dir = realpath((string) ($this->option('out-dir') ?: base_path('ai/evals/results')));
        $absolute = preg_match('#^([A-Za-z]:)?[\\\\/]#', $option) === 1;
        $path = realpath($absolute ? $option : base_path($option));
        $payload = $path !== false && $dir !== false && is_file($path) && str_ends_with($path, '.json')
            && str_starts_with($path, $dir.DIRECTORY_SEPARATOR) ? json_decode((string) file_get_contents($path), true) : null;
        $items = is_array($payload) && ($payload['suite'] ?? null) === 'scan' ? ($payload['items'] ?? null) : null;
        if (! is_array($items) || ! array_is_list($items) || $items === [] || count(array_filter($items, fn ($i) => isset($i['id'], $i['kind'], $i['run'], $i['outcome'], $i['fields']))) !== count($items)) {
            $this->error('--rescore must name a scan result JSON with per-request items (id, kind, run, outcome, fields) inside the result directory.');

            return self::INVALID;
        }

        $stats = (new ScanRunStats($comparer))->build($items);
        $note = ' Re-scored offline: provider errors (for example HTTP 429 from the free-tier quota of 20 requests/day/model) are excluded from field scores, latency and tokens and listed as provider_errors; valid_readings is the real n.';
        $payload['summary'] = self::summaryPayload($stats);
        $payload['scorer_version'] = ScanRunStats::SCORER_VERSION;
        $payload['rescored_at'] = gmdate('c');
        $payload['caveat'] = rtrim((string) ($payload['caveat'] ?? '')).(str_contains((string) ($payload['caveat'] ?? ''), 'Re-scored offline') ? '' : $note);
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).'
');

        $this->report($stats);
        $this->line('re-scored: '.basename($path));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private static function summaryPayload(array $stats): array
    {
        return $stats['overall'] + ['by_kind' => $stats['by_kind'], 'errors_by_code' => $stats['errors_by_code'],
            'unstable' => $stats['unstable'], 'per_image' => $stats['per_image']];
    }

    /** @param array<string, mixed> $stats */
    private function report(array $stats): void
    {
        $groups = ['all' => $stats['overall']] + $stats['by_kind'];
        foreach ($groups as $name => $summary) {
            $this->line("== {$name} ({$summary['images']} requests, {$summary['valid_readings']} valid readings, {$summary['provider_errors']} provider errors ".json_encode($summary['provider_errors_by_code']).')');
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
        }
        $this->line('errors by code '.json_encode($stats['errors_by_code']).'; unstable fields: '.($stats['unstable'] === [] ? 'none' : implode(', ', $stats['unstable'])));
    }

    /**
     * @param  list<array<string, mixed>>  $rows  manifest rows that were run
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $stats
     */
    private function write(string $split, int $runs, string $manifest, array $rows, array $items, array $stats): string
    {
        $path = $this->resultPath('scan');
        $versions = array_values(array_unique(array_filter(array_column($items, 'model_version'))));
        $kinds = array_values(array_unique(array_column($rows, 'kind')));

        $payload = [
            'suite' => 'scan',
            'run_at_utc' => gmdate('c'),
            'manifest' => str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $manifest),
            'split' => $split,
            'runs' => $runs,
            'kind' => implode('+', $kinds),
            'truth_status' => implode('+', array_values(array_unique(array_column($rows, 'truth_status')))),
            'caveat' => in_array('web_sample', $kinds, true)
                ? 'Mostly US/Canadian/European web samples with AI-transcribed, human-unverified truth. Not accuracy on Indonesian packaging.'
                : 'Indonesian label set: real photos have AI-transcribed, human-unverified truth (n is tiny); synthetic labels are exact by construction but clean renders, far easier than photos. Repeated runs measure stability, not independent samples.',
            'model_config' => (string) config('ai.gemini.model'),
            'model_versions_reported' => $versions,
            'prompt_sha256' => hash_file('sha256', base_path('ai/prompts/ocr-system.txt')),
            'requests' => count($items),
            'scorer_version' => ScanRunStats::SCORER_VERSION,
            'summary' => self::summaryPayload($stats),
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
            'routing %s, passed %s, claims %s, citations %s, model-call expectation %s, model calls %d, unsafe claims %d (by construction, see caveat), narrations rejected %s, errors %d; not applicable %d, skipped %d',
            $summary['routing'], $summary['passed'], $summary['claims_ok'], $summary['citations_ok'], $summary['model_calls_ok'],
            $summary['model_calls'], $summary['unsafe_claims'], $summary['narrations_rejected'], $summary['errors'], $summary['not_applicable'], $summary['skipped'],
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
            'caveat' => [
                'scope' => 'One run per case against the 10-food USDA demo catalog. Not a statistic. 9/9-style results are on the applicable cases only; the rest are listed as not applicable, not scored.',
                'mapping' => 'Applicable cases were partly mapped (mapped_fixture) or substituted (substituted_nutrient) onto the demo catalog; see summary.fidelity. This is a demo-catalog smoke test, not a pass of the 12 golden cases.',
                'unsafe_claims' => 'unsafe_claims=0 is close to guaranteed by construction: NarrationGuard already blocks every number-plus-unit before narration reaches the answer, so the count is not an independent safety measurement. narrations_rejected and rejected_unsafe_numbers show how often the guard actually dropped a model text and how many numbers were in the dropped texts.',
                'claims' => 'Expected claim values are derived from the catalog row with the production AnswerRenderer::derive, so the check covers wiring, routing, citations and 2 dp rounding, not the arithmetic itself; the BCMath arithmetic is covered independently by tests/Feature/Ai/AnswerRendererTest.php.',
                'checks' => 'Deterministic checks only. Relevance and honesty of model text still need human review (scores 0-2, spec 08).',
                'latency' => 'For two-turn CHAT-07, latency_ms is the last turn only and model_calls spans both turns.',
            ],
            'model_config' => (string) config('ai.gemini.model'),
            'model_versions_reported' => $versions,
            'prompt_sha256' => self::lfSha(base_path('ai/prompts/chat-system.txt')),
            'golden_sha256' => self::lfSha(base_path('ai/evals/chat-golden.jsonl')),
            'catalog_map_sha256' => self::lfSha(base_path('ai/evals/chat-catalog-map.json')),
            'catalog' => Food::query()->verified()->orderBy('name')->get(['id', 'name', 'current_revision'])
                ->map(fn (Food $f) => ['id' => $f->id, 'name' => $f->name, 'revision' => (int) $f->current_revision])->all(),
            'model_requests' => $summary['model_calls'],
            'summary' => $summary,
            'items' => $items,
        ];
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");

        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }

    /** SHA-256 of the file with CRLF turned into LF, so the hash does not depend on git line-ending conversion. */
    private static function lfSha(string $path): string
    {
        return hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($path)));
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
