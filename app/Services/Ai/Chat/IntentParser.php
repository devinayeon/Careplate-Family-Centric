<?php

namespace App\Services\Ai\Chat;

use App\Actions\Foods\FoodSearchText;
use App\Exceptions\Ai\AiException;
use App\Services\Ai\Scan\Decimal;
use Normalizer;

/**
 * Deterministic message understanding for chat: no model, no database, no side effects.
 * Reads nutrient words, explicit gram amounts, household/volume amounts and clinical wording,
 * and extracts the folded runs of food words that FoodSearch::resolve() matches against the alias whitelist.
 * Throws AiException INVALID_INPUT (amount out of range) or AMOUNT_CONFLICT (body amount_g vs text).
 */
class IntentParser
{
    private const MAX_RUNS = 8;

    private const MAX_RUN_TOKENS = 12;

    /** @param  int|float|string|null  $bodyAmountG  amount_g from the request body, as received */
    public function parse(string $message, int|float|string|null $bodyAmountG = null): Intent
    {
        $body = $bodyAmountG === null ? null : $this->decimal((string) $this->bodyText($bodyAmountG));
        $text = $this->fold($message);

        $grams = $this->gramAmounts($text, $body);
        $text = (string) preg_replace(IntentLexicon::GRAM, ' ', $text);
        $volume = preg_match(IntentLexicon::VOLUME, $text) === 1;
        $household = preg_match(IntentLexicon::household(), $text) === 1;
        $text = (string) preg_replace([IntentLexicon::VOLUME, IntentLexicon::household()], ' ', $text);

        $tokens = explode(' ', FoodSearchText::normalise($text));
        $nutrients = $this->nutrients($tokens);
        $salt = array_intersect($tokens, IntentLexicon::SALT) !== [];
        $joined = ' '.implode(' ', $tokens).' ';
        $runs = $this->runs($tokens);
        $amount = $body ?? ($grams[0] ?? null);
        $notes = $salt && $nutrients !== [] ? [Intent::NOTE_SALT_IS_NOT_SODIUM] : [];

        if (preg_match(IntentLexicon::CLINICAL, $joined) === 1) {
            return $this->intent(Intent::REFERRAL, $nutrients, $amount, $notes, null, []);
        }
        if ($runs === null) {
            return new Intent(Intent::CLARIFICATION, [], $amount, [], Intent::REASON_TOO_LONG);
        }
        if ($amount === null && ($household || $volume)) {
            return $this->intent(Intent::CLARIFICATION, $nutrients, $amount, $notes, $volume && ! $household ? Intent::REASON_VOLUME : Intent::REASON_HOUSEHOLD, $runs);
        }
        if ($body === null && count($grams) > 1) {
            return $this->intent(Intent::CLARIFICATION, $nutrients, $amount, $notes, Intent::REASON_MULTIPLE_AMOUNTS, $runs);
        }
        if ($salt && $nutrients === []) {
            return $this->intent(Intent::CLARIFICATION, $nutrients, $amount, $notes, Intent::REASON_SALT, $runs);
        }
        $education = preg_match(IntentLexicon::EDUCATION, $joined) === 1;
        $asksNumber = preg_match(IntentLexicon::NUMBER_ASK, $joined) === 1;
        if ($education && ! $asksNumber) {
            return $this->intent(Intent::GENERAL, $nutrients, $amount, $notes, null, $runs);
        }
        if ($nutrients !== [] || $asksNumber || $amount !== null) {
            return $this->intent(Intent::LOOKUP, $nutrients, $amount, $notes, null, $runs);
        }

        return $this->intent(Intent::GENERAL, $nutrients, $amount, $notes, null, $runs);
    }

    /**
     * @param  list<string>  $nutrients
     * @param  list<string>  $notes
     * @param  list<string>  $phrases
     */
    private function intent(string $kind, array $nutrients, ?string $amount, array $notes, ?string $reason, array $phrases): Intent
    {
        return new Intent($kind, $nutrients, $amount, $phrases, $reason, $notes);
    }

    private function fold(string $message): string
    {
        $text = class_exists(Normalizer::class) ? (Normalizer::normalize($message, Normalizer::FORM_KC) ?: $message) : $message;

        return mb_strtolower($text, 'UTF-8');
    }

    /**
     * Gram amounts written in the text, validated. With a body amount every text amount must agree with it.
     *
     * @return list<string> distinct canonical decimal strings
     */
    private function gramAmounts(string $text, ?string $body): array
    {
        preg_match_all(IntentLexicon::GRAM, $text, $m);
        $found = [];
        foreach ($m[1] as $raw) {
            $amount = $this->decimal(str_replace(',', '.', $raw));
            if ($body !== null && Decimal::cmp($amount, $body) !== 0) {
                throw AiException::for('AMOUNT_CONFLICT');
            }
            $found[$amount] = $amount;
        }

        return array_values($found);
    }

    /** @return string canonical decimal text of a body amount, rejecting non-numeric shapes */
    private function bodyText(int|float|string $value): string
    {
        if (is_float($value)) {
            if (! is_finite($value)) {
                throw AiException::for('INVALID_INPUT');
            }

            return Decimal::fromNumber($value);
        }

        return trim((string) $value);
    }

    /** Validate "12.5": plain decimal, at most 2 decimals, above 0 and within ai.chat.max_amount_g. */
    private function decimal(string $value): string
    {
        if (preg_match('/^(\d+)(?:\.(\d+))?$/', $value, $m) !== 1) {
            throw AiException::for('INVALID_INPUT');
        }
        $fraction = rtrim($m[2] ?? '', '0');
        if (strlen($fraction) > 2) {
            throw AiException::for('INVALID_INPUT');
        }
        $amount = (ltrim($m[1], '0') ?: '0').($fraction === '' ? '' : '.'.$fraction);
        if (Decimal::cmp($amount, '0') <= 0 || Decimal::cmp($amount, (string) config('ai.chat.max_amount_g', 2000)) > 0) {
            throw AiException::for('INVALID_INPUT');
        }

        return $amount;
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string> column names in contract order
     */
    private function nutrients(array $tokens): array
    {
        $found = [];
        foreach ($tokens as $token) {
            $key = IntentLexicon::NUTRIENTS[$token]
                ?? (str_ends_with($token, 'nya') ? (IntentLexicon::NUTRIENTS[substr($token, 0, -3)] ?? null) : null);
            if ($key !== null) {
                $found[$key] = true;
            }
        }

        return array_values(array_filter(IntentLexicon::ORDER, fn (string $c) => isset($found[$c])));
    }

    /**
     * Runs of consecutive food words. Question words, nutrient words, units and numbers separate runs.
     * Returns null when the message has more food words than a single question reasonably holds.
     *
     * @param  list<string>  $tokens
     * @return list<string>|null
     */
    private function runs(array $tokens): ?array
    {
        $runs = [];
        $current = [];
        foreach ($tokens as $token) {
            if ($token !== '' && ! $this->isSeparator($token)) {
                $current[] = $token;
            } elseif ($current !== []) {
                $runs[] = $current;
                $current = [];
            }
        }
        if ($current !== []) {
            $runs[] = $current;
        }

        if (count($runs) > self::MAX_RUNS) {
            return null;
        }
        foreach ($runs as $run) {
            if (count($run) > self::MAX_RUN_TOKENS) {
                return null;
            }
        }

        return array_map(fn (array $run) => implode(' ', $run), $runs);
    }

    private function isSeparator(string $token): bool
    {
        $base = str_ends_with($token, 'nya') ? substr($token, 0, -3) : $token;

        return ctype_digit($token)
            || in_array($token, IntentLexicon::STOP, true)
            || in_array($token, IntentLexicon::UNITS, true)
            || in_array($token, IntentLexicon::SALT, true)
            || isset(IntentLexicon::NUTRIENTS[$token])
            || isset(IntentLexicon::NUTRIENTS[$base]);
    }
}
