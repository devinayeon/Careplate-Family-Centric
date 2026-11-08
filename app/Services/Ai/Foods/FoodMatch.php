<?php

namespace App\Services\Ai\Foods;

use App\Models\Food;

/**
 * Result of resolving food words against the verified catalog.
 * `candidates` holds at most five verified foods ordered by name, id; it is empty only for no_match.
 */
final class FoodMatch
{
    public const FOUND = 'found';

    public const AMBIGUOUS = 'ambiguous';

    public const NO_MATCH = 'no_match';

    /** @param  list<Food>  $candidates */
    public function __construct(
        public readonly string $state,
        public readonly array $candidates = [],
    ) {}
}
