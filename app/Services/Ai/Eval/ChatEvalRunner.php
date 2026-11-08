<?php

namespace App\Services\Ai\Eval;

use App\Exceptions\Ai\AiException;
use App\Models\ChatSession;
use App\Models\ChatTurn;
use App\Models\Food;
use App\Models\User;
use App\Services\Ai\Chat\AnswerRenderer;
use App\Services\Ai\Chat\ChatComposer;
use App\Services\Ai\Chat\ChatNarrator;
use App\Services\Ai\Chat\ChatService;
use App\Services\Ai\Chat\MemoryBuilder;
use App\Services\Ai\Chat\NarrationGuard;
use App\Services\Ai\Scan\Decimal;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Runs the golden chat cases through ChatService (the code path of POST /ai/v1/chat/sessions/{id}/turns) with a
 * temporary family_head user and one fresh session per case, then deletes every user, session and turn it made.
 * Foods are only read. The model client is a RecordingGeminiClient, so the model calls of every case are counted.
 *
 * A case is not applicable when the demo catalog lacks its premise; it is then reported with the reason and is
 * never relaxed or run against invented data. The comparison itself lives in ChatComparer.
 */
final class ChatEvalRunner
{
    private const COLUMNS = ['energy_kcal', 'fat_g', 'sugars_total_g', 'protein_g', 'carbohydrate_g', 'sodium_mg'];

    /** @var list<int> ids of the temporary users, whatever happens to the run */
    private array $userIds = [];

    private ChatService $chat;

    public function __construct(private readonly ChatComparer $comparer, public readonly RecordingGeminiClient $recorder = new RecordingGeminiClient)
    {
        $narrator = new ChatNarrator($this->recorder, app(NarrationGuard::class), app(MemoryBuilder::class));
        $this->chat = app()->make(ChatService::class, ['composer' => app()->make(ChatComposer::class, ['narrator' => $narrator])]);
    }

    /**
     * Golden cases joined with the catalog map by id.
     *
     * @return list<array<string, mixed>>
     */
    public static function loadCases(string $goldenPath, string $mapPath): array
    {
        $map = [];
        foreach ((array) json_decode((string) file_get_contents($mapPath), true, 512, JSON_THROW_ON_ERROR)['cases'] as $case) {
            $map[$case['id']] = $case;
        }
        $cases = [];
        foreach (array_filter(explode("\n", (string) file_get_contents($goldenPath))) as $line) {
            $golden = (array) json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            if (! isset($map[$golden['id']])) {
                throw new RuntimeException('No catalog mapping for '.$golden['id']);
            }
            $cases[] = ['golden' => $golden] + $map[$golden['id']];
        }

        return $cases;
    }

    /**
     * @param  list<array<string, mixed>>  $cases
     * @return list<array<string, mixed>>
     */
    public function run(array $cases, bool $modelAvailable, ?callable $progress = null): array
    {
        $items = [];
        try {
            foreach ($cases as $case) {
                $items[] = $this->runCase($case, $modelAvailable);
                if ($progress !== null) {
                    $progress($items[array_key_last($items)]);
                }
            }
        } finally {
            $this->cleanup();
        }

        return $items;
    }

    /** @return array<string, mixed> */
    public function runCase(array $case, bool $modelAvailable): array
    {
        $item = [
            'id' => $case['id'], 'group' => $case['golden']['group'] ?? null, 'golden_input' => $case['golden']['input'] ?? null,
            'golden_expected' => $case['golden']['expected'] ?? null, 'fidelity' => $case['fidelity'] ?? null, 'note' => $case['note'] ?? null,
            'messages' => array_column($case['turns'] ?? [], 'message'),
            'applicable' => true, 'skipped' => null, 'reason' => null, 'synthetic_coverage' => $case['synthetic'] ?? null,
        ];
        if (($case['applicable'] ?? false) !== true) {
            return $this->unrun($item, false, $case['reason'] ?? 'not applicable');
        }
        [$expect, $reason] = $this->resolve($case);
        if ($expect === null) {
            return $this->unrun($item, false, $reason);
        }
        if (! $modelAvailable && (($expect['model_calls'] ?? 0) >= 1 || ($expect['model_calls_max'] ?? 0) >= 1)) {
            return $this->unrun($item + ['expected' => $expect], true, 'AI is disabled or has no key; this case needs the model.');
        }

        $user = $this->tempUser();
        $canary = null;
        if ($case['canary'] ?? false) {
            $canary = $this->plantCanary();
            $expect['answer_not_contains'] = array_map(fn (string $w) => str_replace('{canary}', $canary, $w), $expect['answer_not_contains']);
        }
        $run = $this->turns($user, $case);
        $compared = $this->comparer->compare($expect, $run['response'], $run['error'], $run['model_calls'], $run['narration']);

        return array_merge($item, $compared, [
            'expected' => $expect,
            'answer' => $run['response']['answer'] ?? null,
            'warnings' => $run['response']['warnings'] ?? [],
            'model_calls' => $run['model_calls'],
            'latency_ms' => $run['latency_ms'],
            'provider_latency_ms' => $run['provider_latency_ms'],
            'model_version' => $run['model_version'],
            'usage' => $run['usage'],
            'turns' => count($case['turns']),
            'outcome' => $compared['pass'] ? 'pass' : 'fail',
        ]);
    }

    /**
     * Sends every turn of the case into one fresh session; the result belongs to the last turn.
     *
     * @return array<string, mixed>
     */
    private function turns(User $user, array $case): array
    {
        $session = ChatSession::create(['user_id' => $user->id, 'title' => null, 'state' => 'active', 'generation' => 1, 'next_sequence' => 1]);
        $this->recorder->reset();
        $response = null;
        $error = null;
        $start = 0;
        foreach ($case['turns'] as $i => $turn) {
            $start = hrtime(true);
            try {
                $response = $this->chat->send($user, $session->refresh(), [
                    'message' => $turn['message'], 'client_turn_id' => (string) Str::uuid(),
                    'food_id' => null, 'amount_g' => $turn['amount_g'] ?? null,
                ], 'req_eval_'.$case['id'].'_'.($i + 1));
            } catch (AiException $e) {
                $response = null;
                $error = $e->errorCode;
            } catch (Throwable) {
                $response = null;
                $error = 'INTERNAL';
            }
            if ($error !== null) {
                break;
            }
        }
        $latency = (int) round((hrtime(true) - $start) / 1_000_000);

        $text = $this->recorder->last?->json['text'] ?? null;
        $narration = is_string($text) && $response !== null && trim($text) !== '' && str_contains($response['answer'], trim($text)) ? trim($text) : null;

        return [
            'response' => $response, 'error' => $error, 'narration' => $narration, 'latency_ms' => $latency,
            'model_calls' => $this->recorder->calls,
            'provider_latency_ms' => $this->recorder->results === [] ? null : array_sum(array_map(fn ($r) => $r->latencyMs, $this->recorder->results)),
            'model_version' => $this->recorder->last?->modelVersion,
            'usage' => $this->usage(),
        ];
    }

    /**
     * Concrete expectation from the map entry and the live catalog row; [null, reason] when the premise is missing.
     *
     * @return array{0: array<string, mixed>|null, 1: string|null}
     */
    private function resolve(array $case): array
    {
        $e = $case['expect'] ?? [];
        $expect = [
            'modes' => $e['modes'], 'retrieval_status' => $e['retrieval_status'] ?? null, 'claims' => [], 'citations' => [],
            'model_calls' => $e['model_calls'] ?? null, 'model_calls_max' => $e['model_calls_max'] ?? null,
            'notice' => $e['notice'] ?? null, 'answer_contains' => $e['answer_contains'] ?? [], 'answer_not_contains' => $e['answer_not_contains'] ?? [],
        ];
        if (! isset($e['food'])) {
            return [$expect, null];
        }
        $food = Food::query()->verified()->where('name', $e['food'])->first();
        if ($food === null) {
            return [null, 'The demo catalog has no verified food named "'.$e['food'].'".'];
        }
        foreach ($e['requires_null'] ?? [] as $column) {
            if ($food->{$column} !== null) {
                return [null, $food->name.' has a value for '.$column.', so the unknown-value premise does not hold.'];
            }
        }
        $amount = (string) ($e['amount_g'] ?? AnswerRenderer::DEFAULT_AMOUNT_G);
        foreach ($e['nutrients'] === 'known' ? self::COLUMNS : $e['nutrients'] as $column) {
            if ($food->{$column} !== null) {
                $expect['claims'][] = [
                    'food_id' => (string) $food->getKey(), 'revision' => (int) $food->current_revision, 'nutrient' => $column,
                    'value' => Decimal::round(AnswerRenderer::derive((string) $food->{$column}, $amount), 2),
                ];
            }
        }
        $expect['citations'] = [['food_id' => (string) $food->getKey(), 'revision' => (int) $food->current_revision]];

        return [$expect, null];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function unrun(array $item, bool $skippedForModel, string $reason): array
    {
        return array_merge($item, [
            'applicable' => $skippedForModel, 'skipped' => $skippedForModel ? 'ai_disabled' : null, 'reason' => $reason,
            'outcome' => $skippedForModel ? 'skipped' : 'not_applicable', 'pass' => false, 'routing_ok' => false, 'claims_ok' => false,
            'citations_ok' => false, 'model_calls_ok' => false, 'unsafe_claims' => 0, 'error_code' => null, 'model_calls' => 0,
            'latency_ms' => null, 'usage' => null, 'failures' => [],
        ]);
    }

    /** @return array{prompt: ?int, candidates: ?int, thoughts: ?int, total: ?int}|null null when no call reported usage */
    private function usage(): ?array
    {
        if ($this->recorder->results === []) {
            return null;
        }
        $sum = ['prompt' => null, 'candidates' => null, 'thoughts' => null, 'total' => null];
        foreach ($this->recorder->results as $result) {
            foreach ($sum as $field => $value) {
                if (($result->usage[$field] ?? null) !== null) {
                    $sum[$field] = ($value ?? 0) + $result->usage[$field];
                }
            }
        }

        return $sum;
    }

    private function tempUser(): User
    {
        $user = User::create(['name' => 'AI Eval', 'email' => 'eval-'.bin2hex(random_bytes(6)).'@eval.invalid', 'password' => Str::random(40)]);
        $this->userIds[] = $user->id;
        $user->assignRole('family_head');
        if (! $user->can('nutrition-chat')) {
            throw new RuntimeException('The family_head role lacks the nutrition-chat permission; run the permission seeder.');
        }

        return $user;
    }

    /** A second user with a finished turn whose text must never show up in the first user's answer. */
    private function plantCanary(): string
    {
        $canary = 'CANARY-'.bin2hex(random_bytes(6));
        $session = ChatSession::create(['user_id' => $this->tempUser()->id, 'title' => null, 'state' => 'active', 'generation' => 1, 'next_sequence' => 2]);
        ChatTurn::create([
            'session_id' => $session->id, 'client_turn_id' => (string) Str::uuid(), 'sequence' => 1, 'request_hash' => 'eval', 'status' => 'completed',
            'token' => Str::random(40), 'generation' => 1, 'lease_expires_at' => now(), 'message' => $canary,
            'response' => ['answer' => $canary], 'citations' => [], 'food_refs' => [],
        ]);

        return $canary;
    }

    /** Deletes the temporary users with their sessions and turns, then proves nothing is left. */
    public function cleanup(): void
    {
        if ($this->userIds === []) {
            return;
        }
        $sessions = ChatSession::query()->whereIn('user_id', $this->userIds)->pluck('id')->all();
        ChatTurn::query()->whereIn('session_id', $sessions)->delete();
        ChatSession::query()->whereIn('id', $sessions)->delete();
        User::query()->whereIn('id', $this->userIds)->get()->each->delete();

        if (User::query()->whereIn('id', $this->userIds)->exists() || ChatSession::query()->whereIn('user_id', $this->userIds)->exists()
            || ChatTurn::query()->whereIn('session_id', $sessions)->exists()) {
            throw new RuntimeException('Eval cleanup left temporary rows behind.');
        }
        $this->userIds = [];
    }
}
