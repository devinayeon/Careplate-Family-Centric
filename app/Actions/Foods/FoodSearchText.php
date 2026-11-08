<?php

namespace App\Actions\Foods;

use Normalizer;

/**
 * Builds the single `foods.search_text` column the catalog search reads: lowercase name, aliases and
 * source description, Unicode-normalised, punctuation folded to single spaces, repeats dropped.
 */
class FoodSearchText
{
    /** @param  list<string>  $aliases */
    public static function build(string $name, array $aliases, ?string $description): string
    {
        $parts = [];
        foreach ([$name, ...$aliases, (string) $description] as $text) {
            $normalised = self::normalise($text);
            if ($normalised !== '' && ! in_array($normalised, $parts, true)) {
                $parts[] = $normalised;
            }
        }

        return implode(' ', $parts);
    }

    /** Same folding for stored text and for search queries. */
    public static function normalise(string $text): string
    {
        if (class_exists(Normalizer::class)) {
            $text = Normalizer::normalize($text, Normalizer::FORM_KC) ?: $text;
        }
        $text = mb_strtolower($text, 'UTF-8');

        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text));
    }
}
