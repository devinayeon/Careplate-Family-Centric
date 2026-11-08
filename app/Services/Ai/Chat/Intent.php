<?php

namespace App\Services\Ai\Chat;

/**
 * What IntentParser understood from one message. Holds no user text beyond the folded food phrases.
 *
 * kind: referral | clarification | lookup | general
 *  - referral: clinical, diagnosis or dose wording; the server answers with a hand-off.
 *  - clarification: `reason` says what to ask (a REASON_* constant).
 *  - lookup: a nutrient/quantity question about foods; `foodPhrases` may be empty (use memory or ask).
 *  - general: education or chit-chat; `foodPhrases` is still filled so a caller may try a lookup for a named food.
 */
final class Intent
{
    public const REFERRAL = 'referral';

    public const CLARIFICATION = 'clarification';

    public const LOOKUP = 'lookup';

    public const GENERAL = 'general';

    public const REASON_HOUSEHOLD = 'household_amount';

    public const REASON_VOLUME = 'volume_amount';

    public const REASON_SALT = 'salt';

    public const REASON_MULTIPLE_AMOUNTS = 'multiple_amounts';

    public const REASON_TOO_LONG = 'too_long';

    public const NOTE_SALT_IS_NOT_SODIUM = 'salt_is_not_sodium';

    /**
     * @param  list<string>  $nutrients  requested columns in contract order; empty = not specified (renderer answers all six)
     * @param  ?string  $amountG  effective grams as a decimal string (body amount_g or text), null = not given
     * @param  list<string>  $foodPhrases  folded runs of food words (question words, nutrients and amounts removed)
     * @param  list<string>  $notes  NOTE_* constants for the renderer
     */
    public function __construct(
        public readonly string $kind,
        public readonly array $nutrients = [],
        public readonly ?string $amountG = null,
        public readonly array $foodPhrases = [],
        public readonly ?string $reason = null,
        public readonly array $notes = [],
    ) {}
}
