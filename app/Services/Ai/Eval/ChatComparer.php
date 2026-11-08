<?php

namespace App\Services\Ai\Eval;

use App\Services\Ai\Chat\NarrationLexicon;
use App\Services\Ai\Scan\Decimal;
use Normalizer;

/**
 * Pure comparison logic of `ai:eval chat`: one resolved expectation against one ChatResponse, and the summary over
 * all cases. No database, no model, no clock. Numbers are compared at two decimals with BCMath.
 *
 * expect: {modes: list<string>, retrieval_status: ?string, claims: list<{food_id, revision, nutrient, value}>,
 *          citations: list<{food_id, revision}>, model_calls: ?int, model_calls_max: ?int, notice: ?bool,
 *          answer_contains: list<string>, answer_not_contains: list<string>}
 */
final class ChatComparer
{
    /**
     * @param  array<string, mixed>  $expect
     * @param  array<string, mixed>|null  $response  ChatResponse, null when the turn failed
     * @param  string|null  $narration  the validated model narration contained in the answer, null when none
     * @return array<string, mixed>
     */
    public function compare(array $expect, ?array $response, ?string $errorCode, int $modelCalls, ?string $narration): array
    {
        $unsafe = $narration === null ? 0 : $this->unsafeClaims($narration, $this->supplied($response));
        $calls = $this->callsOk($expect, $modelCalls);
        if ($response === null || $errorCode !== null) {
            return $this->failed($errorCode ?? 'NO_RESPONSE', $calls, $unsafe);
        }

        $claims = $this->claims($response['claims'] ?? []);
        $citations = $this->citations($response['citations'] ?? []);
        $answer = (string) ($response['answer'] ?? '');
        $modeOk = in_array($response['mode'] ?? null, $expect['modes'], true);
        $statusOk = ($expect['retrieval_status'] ?? null) === null || $expect['retrieval_status'] === ($response['retrieval_status'] ?? null);
        $check = [
            'mode_ok' => $modeOk,
            'status_ok' => $statusOk,
            'claims_ok' => $this->claimKeys($claims) === $this->claimKeys($this->claims($expect['claims'] ?? [], 'value')),
            'citations_ok' => $this->refKeys($citations) === $this->refKeys($expect['citations'] ?? []),
            'model_calls_ok' => $calls,
            'notice_ok' => ($expect['notice'] ?? null) === null || $expect['notice'] === (($response['general_knowledge_notice'] ?? null) !== null && $response['general_knowledge_notice'] !== ''),
            'content_ok' => $this->contentOk($answer, $expect),
            'unsafe_ok' => $unsafe === 0,
        ];
        $failures = array_keys(array_filter($check, fn (bool $ok) => ! $ok));

        return [
            'actual_mode' => $response['mode'] ?? null,
            'actual_retrieval_status' => $response['retrieval_status'] ?? null,
            'routing_ok' => $modeOk && $statusOk,
            'claims' => array_map(fn (array $c) => ['nutrient' => $c['nutrient'], 'value' => $c['value']], $claims),
            'citations' => array_map(fn (array $c) => ['food_id' => $c['food_id'], 'revision' => $c['revision']], $citations),
            'unsafe_claims' => $unsafe,
            'error_code' => null,
            'pass' => $failures === [],
            'failures' => array_map(fn (string $k) => $k === 'unsafe_ok' ? 'unsafe_claims:'.$unsafe : $k, $failures),
        ] + $check;
    }

    /**
     * Number-plus-unit mentions in model text that the server did not supply. `$allowed` holds decimal strings
     * of server-derived numbers ("22.5"); a mention whose digits equal one of them is not counted.
     *
     * @param  list<string>  $allowed
     */
    public function unsafeClaims(string $text, array $allowed = []): int
    {
        $folded = class_exists(Normalizer::class) ? (Normalizer::normalize($text, Normalizer::FORM_KC) ?: $text) : $text;
        $folded = mb_strtolower((string) preg_replace('/\p{Cf}+/u', '', $folded));
        $allowedKeys = array_map(fn (string $a) => Decimal::trim($a), $allowed);
        if (preg_match_all(NarrationLexicon::nutritionNumber(), $folded, $m) === false) {
            return 1;
        }

        $count = 0;
        foreach ($m[0] as $mention) {
            if (preg_match('/\p{Nd}+(?:[.,]\p{Nd}+)*/u', $mention, $digits) === 1
                && in_array(Decimal::trim(str_replace(',', '.', $digits[0])), $allowedKeys, true)) {
                continue;
            }
            $count++;
        }

        return $count;
    }

    /**
     * @param  list<array<string, mixed>>  $items  per-case results (flat, as written to the result file)
     * @return array<string, mixed>
     */
    public function summarize(array $items): array
    {
        $run = array_values(array_filter($items, fn (array $i) => $i['applicable'] && $i['skipped'] === null));
        $n = count($run);
        $count = fn (string $key) => count(array_filter($run, fn (array $i) => $i[$key]));
        $latency = fn (bool $model) => $this->latency(array_map(
            fn (array $i) => (int) $i['latency_ms'],
            array_values(array_filter($run, fn (array $i) => ($i['model_calls'] > 0) === $model)),
        ));

        return [
            'cases' => count($items),
            'applicable' => $n,
            'not_applicable' => count(array_filter($items, fn (array $i) => ! $i['applicable'])),
            'skipped' => count(array_filter($items, fn (array $i) => $i['applicable'] && $i['skipped'] !== null)),
            'routing' => $count('routing_ok').'/'.$n,
            'passed' => $count('pass').'/'.$n,
            'claims_ok' => $count('claims_ok').'/'.$n,
            'citations_ok' => $count('citations_ok').'/'.$n,
            'model_calls_ok' => $count('model_calls_ok').'/'.$n,
            'unsafe_claims' => array_sum(array_column($run, 'unsafe_claims')),
            'errors' => count(array_filter($run, fn (array $i) => $i['error_code'] !== null)),
            'model_calls' => array_sum(array_column($run, 'model_calls')),
            'latency_ms' => ['server' => $latency(false), 'model' => $latency(true)],
            'usage' => $this->usage($run),
        ];
    }

    /** @return array<string, mixed> */
    private function failed(string $code, bool $calls, int $unsafe): array
    {
        return [
            'actual_mode' => null, 'actual_retrieval_status' => null, 'routing_ok' => false, 'claims' => [], 'citations' => [],
            'unsafe_claims' => $unsafe, 'error_code' => $code, 'pass' => false, 'failures' => ['error:'.$code],
            'mode_ok' => false, 'status_ok' => false, 'claims_ok' => false, 'citations_ok' => false, 'model_calls_ok' => $calls,
            'notice_ok' => false, 'content_ok' => false, 'unsafe_ok' => $unsafe === 0,
        ];
    }

    /**
     * Numbers the server itself put into the response claims (canonical, requested, derived), as decimal strings.
     *
     * @param  array<string, mixed>|null  $response
     * @return list<string>
     */
    private function supplied(?array $response): array
    {
        $numbers = [];
        foreach ((array) ($response['claims'] ?? []) as $claim) {
            foreach (['canonical_value', 'requested_amount', 'derived_value'] as $key) {
                if (isset($claim[$key]) && is_numeric($claim[$key])) {
                    $numbers[] = Decimal::fromNumber($claim[$key] + 0);
                }
            }
        }

        return $numbers;
    }

    /** @param  array<string, mixed>  $expect */
    private function callsOk(array $expect, int $calls): bool
    {
        if (($expect['model_calls'] ?? null) !== null) {
            return $calls === $expect['model_calls'];
        }

        return ($expect['model_calls_max'] ?? null) === null || $calls <= $expect['model_calls_max'];
    }

    /** @param  array<string, mixed>  $expect */
    private function contentOk(string $answer, array $expect): bool
    {
        foreach ($expect['answer_contains'] ?? [] as $word) {
            if (mb_stripos($answer, $word) === false) {
                return false;
            }
        }
        foreach ($expect['answer_not_contains'] ?? [] as $word) {
            if (mb_stripos($answer, $word) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Claims reduced to {food_id, revision, nutrient, value at 2dp}.
     *
     * @param  list<array<string, mixed>>  $claims
     * @param  string  $valueKey  'derived_value' for a response, 'value' for an expectation
     * @return list<array{food_id: string, revision: int, nutrient: string, value: string}>
     */
    private function claims(array $claims, string $valueKey = 'derived_value'): array
    {
        return array_map(fn (array $c) => [
            'food_id' => (string) $c['food_id'], 'revision' => (int) $c['revision'], 'nutrient' => (string) $c['nutrient'],
            'value' => Decimal::round(is_string($c[$valueKey]) ? $c[$valueKey] : Decimal::fromNumber($c[$valueKey]), 2),
        ], $claims);
    }

    /**
     * @param  list<array<string, mixed>>  $citations
     * @return list<array{food_id: string, revision: int}>
     */
    private function citations(array $citations): array
    {
        return array_map(fn (array $c) => ['food_id' => (string) $c['food_id'], 'revision' => (int) $c['revision']], $citations);
    }

    /**
     * @param  list<array{food_id: string, revision: int, nutrient: string, value: string}>  $claims
     * @return list<string>
     */
    private function claimKeys(array $claims): array
    {
        $keys = array_map(fn (array $c) => $c['food_id'].'@'.$c['revision'].'|'.$c['nutrient'].'='.$c['value'], $claims);
        sort($keys);

        return $keys;
    }

    /**
     * @param  list<array<string, mixed>>  $refs
     * @return list<string>
     */
    private function refKeys(array $refs): array
    {
        $keys = array_values(array_unique(array_map(fn (array $c) => $c['food_id'].'@'.$c['revision'], $refs)));
        sort($keys);

        return $keys;
    }

    /**
     * Nearest-rank percentiles (rank = ceil(p/100 * n)).
     *
     * @param  list<int>  $values
     * @return array{n: int, p50: ?int, p95: ?int, max: ?int}
     */
    private function latency(array $values): array
    {
        sort($values);
        $n = count($values);
        $at = fn (int $p) => $n === 0 ? null : $values[max(1, (int) ceil($p / 100 * $n)) - 1];

        return ['n' => $n, 'p50' => $at(50), 'p95' => $at(95), 'max' => $n === 0 ? null : $values[$n - 1]];
    }

    /**
     * Sum of provider-reported usage; a field no case reported stays null (unknown is not zero).
     *
     * @param  list<array<string, mixed>>  $run
     * @return array<string, int|null>
     */
    private function usage(array $run): array
    {
        $sum = ['prompt' => null, 'candidates' => null, 'thoughts' => null, 'total' => null];
        foreach ($run as $item) {
            foreach ($sum as $field => $value) {
                $reported = $item['usage'][$field] ?? null;
                if ($reported !== null) {
                    $sum[$field] = ($value ?? 0) + $reported;
                }
            }
        }

        return $sum;
    }
}
