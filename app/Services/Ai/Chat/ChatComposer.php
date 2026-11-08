<?php

namespace App\Services\Ai\Chat;

use App\Exceptions\Ai\AiException;
use App\Models\ChatSession;
use App\Models\Food;
use App\Services\Ai\Foods\FoodMatch;
use App\Services\Ai\Foods\FoodSearch;

/**
 * Server-side routing of one chat turn (spec 04 section B): parse the message, identify the food, choose the mode.
 *  - database: server template, no model call, so facts survive a provider outage;
 *  - clarification / no_match / referral: server templates, no model call;
 *  - general (no food) and mixed (database facts + education): exactly one model call through ChatNarrator.
 * Food rows are always read fresh here; numbers in memory are never reused. Every number and citation comes
 * from AnswerRenderer; the model only contributes narration that NarrationGuard accepted.
 */
class ChatComposer
{
    public const MIXED_FALLBACK_WARNING = 'Penjelasan umum tidak tersedia saat ini; yang ditampilkan hanya data dari database.';

    public function __construct(
        private readonly IntentParser $parser,
        private readonly FoodSearch $search,
        private readonly AnswerRenderer $renderer,
        private readonly MemoryBuilder $memory,
        private readonly ChatNarrator $narrator,
    ) {}

    /**
     * @param  array{message: string, food_id: string|null, amount_g: string|null}  $input
     *
     * @throws AiException AMOUNT_CONFLICT, INVALID_INPUT, KNOWLEDGE_UNAVAILABLE, or a provider error on a pure general question
     */
    public function compose(ChatSession $session, array $input): ChatAnswer
    {
        $intent = $this->parser->parse($input['message'], $input['amount_g']);

        return match ($intent->kind) {
            Intent::REFERRAL => new ChatAnswer($this->renderer->referral()),
            Intent::CLARIFICATION => $this->clarify($session, $intent, $input['food_id']),
            Intent::LOOKUP => $this->lookup($session, $intent, $input['food_id']),
            default => $this->general($session, $intent, $input),
        };
    }

    private function clarify(ChatSession $session, Intent $intent, ?string $foodId): ChatAnswer
    {
        $match = $intent->reason === Intent::REASON_TOO_LONG ? null : $this->identify($session, $intent, $foodId, true);
        $food = $match?->state === FoodMatch::FOUND ? $match->candidates[0] : null;

        return new ChatAnswer($this->renderer->clarification((string) $intent->reason, $food), $food === null ? [] : [self::ref($food)]);
    }

    private function lookup(ChatSession $session, Intent $intent, ?string $foodId): ChatAnswer
    {
        $match = $this->identify($session, $intent, $foodId, true);

        return match ($match?->state) {
            FoodMatch::FOUND => $this->database($match->candidates[0], $intent),
            FoodMatch::AMBIGUOUS => new ChatAnswer($this->renderer->ambiguous($match->candidates)),
            FoodMatch::NO_MATCH => new ChatAnswer($this->renderer->noMatch()),
            default => new ChatAnswer($this->renderer->clarification('missing_food')),
        };
    }

    /** @param  array{message: string, food_id: string|null, amount_g: string|null}  $input */
    private function general(ChatSession $session, Intent $intent, array $input): ChatAnswer
    {
        $named = $intent->foodPhrases !== [] || $input['food_id'] !== null;
        $match = $named ? $this->identify($session, $intent, $input['food_id'], false) : null;

        if ($match?->state === FoodMatch::AMBIGUOUS) {
            return new ChatAnswer($this->renderer->ambiguous($match->candidates));
        }
        if ($match?->state === FoodMatch::FOUND) {
            return $this->mixed($session, $match->candidates[0], $intent, $input['message']);
        }

        // Education about no catalog food: model narration only, no claims, no citations.
        $narration = $this->narrator->narrate($session, $input['message'], 'katalog=tidak diperlukan');
        $mode = match ($narration['kind']) {
            'referral' => ['referral', 'not_needed', AnswerRenderer::GENERAL_NOTICE],
            'clarification' => ['clarification', 'ambiguous', null],
            default => ['general', 'not_needed', AnswerRenderer::GENERAL_NOTICE],
        };

        return new ChatAnswer(['mode' => $mode[0], 'retrieval_status' => $mode[1], 'answer' => $narration['text'],
            'claims' => [], 'citations' => [], 'general_knowledge_notice' => $mode[2], 'warnings' => []]);
    }

    /** Database facts plus one model narration; any model problem leaves the database template alone. */
    private function mixed(ChatSession $session, Food $food, Intent $intent, string $question): ChatAnswer
    {
        $base = $this->renderer->database($food, $intent->nutrients, $intent->amountG, $intent->notes);
        $refs = [self::ref($food)];
        try {
            $narration = $this->narrator->narrate($session, $question, 'katalog=ditemukan; makanan='.$food->name, $base['citations'][0]['source_reference']);
        } catch (AiException $e) {
            return $this->withoutNarration($base, $refs, $e->errorCode);
        }

        $answer = $base['answer']."\n\n".$narration['text'];
        if (mb_strlen($answer) > 6000) {
            return $this->withoutNarration($base, $refs, 'AI_OUTPUT_INVALID');
        }

        return new ChatAnswer(array_merge($base, ['mode' => 'mixed', 'answer' => $answer, 'general_knowledge_notice' => AnswerRenderer::GENERAL_NOTICE]), $refs);
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  list<array{food_id: string, revision: int}>  $refs
     */
    private function withoutNarration(array $base, array $refs, string $code): ChatAnswer
    {
        $base['warnings'][] = self::MIXED_FALLBACK_WARNING;

        return new ChatAnswer($base, $refs, $code);
    }

    private function database(Food $food, Intent $intent): ChatAnswer
    {
        return new ChatAnswer($this->renderer->database($food, $intent->nutrients, $intent->amountG, $intent->notes), [self::ref($food)]);
    }

    /**
     * Which food does this turn mean? A body food_id (verified only) beats words; text that names a DIFFERENT
     * food than the body is AMOUNT_CONFLICT (spec 04: body vs text conflict). Words that match nothing do not
     * contradict a body food (they may be filler). Without words and body, the follow-up entity of the last
     * completed turn is re-fetched, but only when `$remember` is true.
     *
     * @return FoodMatch|null null = no food was named at all
     */
    private function identify(ChatSession $session, Intent $intent, ?string $foodId, bool $remember): ?FoodMatch
    {
        $named = $intent->foodPhrases === [] ? null : $this->search->resolve($intent->foodPhrases);

        if ($foodId !== null) {
            $food = $this->search->find($foodId);
            if ($food === null) {
                return new FoodMatch(FoodMatch::NO_MATCH);
            }
            if ($named !== null && $named->state !== FoodMatch::NO_MATCH
                && ! in_array($food->getKey(), array_map(fn (Food $f) => $f->getKey(), $named->candidates), true)) {
                throw AiException::for('AMOUNT_CONFLICT');
            }

            return new FoodMatch(FoodMatch::FOUND, [$food]);
        }
        if ($named !== null) {
            return $named;
        }

        $carried = $remember ? $this->memory->carriedFoodId($session) : null;
        $food = $carried === null ? null : $this->search->find($carried);

        return $food === null ? null : new FoodMatch(FoodMatch::FOUND, [$food]);
    }

    /** @return array{food_id: string, revision: int} */
    private static function ref(Food $food): array
    {
        return ['food_id' => (string) $food->getKey(), 'revision' => (int) $food->current_revision];
    }
}
