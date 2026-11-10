<?php

namespace App\Services\Ai\Chat;

use App\Models\ChatSession;
use App\Models\ChatTurn;
use Illuminate\Database\Eloquent\Collection;

/**
 * Owner-scoped, server-built conversation memory. It reads only COMPLETED turns of the one session it is given
 * (pending, failed and other sessions never appear), inside the retention window.
 *
 *  - history(): the last `ai.context.history_turns` pairs, oldest first, for the model prompt. Oldest pairs are
 *    dropped while a conservative estimate (ceil(characters / 3) tokens) of memory + question + server facts
 *    exceeds `ai.context.text_token_budget`. Digits in stored answers are redacted: numbers in history are not facts.
 *  - carriedFoodId(): the follow-up entity, only when the last completed turn identified exactly one food.
 *    It is an id, never a number; the caller re-fetches the current verified food.
 */
class MemoryBuilder
{
    public const REDACTED = '[angka]';

    /** @return list<array{user: string, assistant: string}> */
    public function history(ChatSession $session, string $question, string $facts): array
    {
        $pairs = [];
        foreach ($this->recent($session, (int) config('ai.context.history_turns', 6))->reverse() as $turn) {
            $answer = is_array($turn->response) && is_string($turn->response['answer'] ?? null) ? $turn->response['answer'] : null;
            if (is_string($turn->message) && $turn->message !== '' && $answer !== null) {
                $pairs[] = ['user' => $turn->message, 'assistant' => self::redact($answer)];
            }
        }

        $budget = (int) config('ai.context.text_token_budget', 6000);
        while ($pairs !== [] && self::estimate($pairs, $question, $facts) > $budget) {
            array_shift($pairs);
        }

        return $pairs;
    }

    /** Food id of the follow-up entity: exactly one distinct food in the last completed turn's food_refs. */
    public function carriedFoodId(ChatSession $session): ?string
    {
        $last = $this->recent($session, 1)->first();

        return $last !== null && is_array($last->food_refs) ? TurnSlot::soleFood($last->food_refs) : null;
    }

    /**
     * Conservative token estimate: one token per three characters, rounded up.
     *
     * @param  list<array{user: string, assistant: string}>  $pairs
     */
    public static function estimate(array $pairs, string $question, string $facts): int
    {
        $characters = mb_strlen($question) + mb_strlen($facts);
        foreach ($pairs as $pair) {
            $characters += mb_strlen($pair['user']) + mb_strlen($pair['assistant']);
        }

        return (int) ceil($characters / 3);
    }

    public static function redact(string $text): string
    {
        return (string) preg_replace('/\p{Nd}[\p{Nd}.,]*/u', self::REDACTED, $text);
    }

    /** @return Collection<int, ChatTurn> newest first */
    private function recent(ChatSession $session, int $limit): Collection
    {
        return ChatTurn::query()
            ->where('session_id', $session->getKey())
            ->where('status', TurnGate::COMPLETED)
            ->where('created_at', '>=', TurnGate::retentionCutoff())
            ->orderByDesc('sequence')
            ->limit(max(1, $limit))
            ->get();
    }
}
