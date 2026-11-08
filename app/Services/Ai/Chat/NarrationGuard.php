<?php

namespace App\Services\Ai\Chat;

use Normalizer;

/**
 * Checks what the model returned before it can reach a user. Two steps, no repair:
 *  - output(): the decoded JSON must match ai/schemas/provider-chat.json exactly (kind enum + text string,
 *    no other keys);
 *  - safe(): the text carries no nutrition number, link, citation marker or source name the server did not supply.
 * Product numbers and citations are built by the server; narration is education only.
 */
final class NarrationGuard
{
    public const KINDS = ['educational', 'clarification', 'referral'];

    public const MAX_CHARACTERS = 2000;

    private const NUMBER_WORDS = 'seratus|seribu|sejuta|nol|satu|dua|tiga|empat|lima|enam|tujuh|delapan|sembilan|sepuluh|sebelas|belas|puluh|ratus|ribu|juta|setengah|seperempat|one|two|three|four|five|six|seven|eight|nine|ten|hundred|thousand|half';

    private const UNITS = 'mg|mcg|µg|μg|ug|kg|g|gr|gram|grams|kilogram|miligram|mikrogram|kcal|kkal|kal|kalori|calories?|kj|kilojoule|joule|ml|liter|persen|percent|%|iu|sdm|sdt';

    /** Authorities and publishers a model may like to cite; allowed only when the server's own source text names them. */
    private const SOURCES = [
        'usda', 'fooddata', 'fdc', 'tkpi', 'kemenkes', 'kementerian kesehatan', 'bpom', 'who', 'fao', 'fda',
        'pubmed', 'wikipedia', 'mayo clinic', 'halodoc', 'alodokter', 'healthline', 'webmd', 'cochrane', 'idai',
        'persagi', 'nutrition facts', 'cnn', 'kompas', 'detik',
    ];

    /**
     * @param  array<mixed>  $json  decoded model JSON
     * @return array{kind: string, text: string}|null null when the shape is not the provider schema
     */
    public function output(array $json): ?array
    {
        if (array_keys($json) !== ['kind', 'text'] && array_keys($json) !== ['text', 'kind']) {
            return null;
        }
        $kind = $json['kind'];
        $text = $json['text'];
        if (! is_string($kind) || ! in_array($kind, self::KINDS, true) || ! is_string($text)) {
            return null;
        }
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > self::MAX_CHARACTERS) {
            return null;
        }

        return ['kind' => $kind, 'text' => $text];
    }

    /** @param  string  $allowedSources  server-supplied source text (citation source_reference); names in it are allowed */
    public function safe(string $text, string $allowedSources = ''): bool
    {
        $folded = mb_strtolower(Normalizer::normalize($text, Normalizer::FORM_KC) ?: $text);

        return ! $this->hasNutritionNumber($folded) && ! $this->hasLink($folded)
            && ! $this->hasCitationMarker($folded) && ! $this->hasForeignSource($folded, mb_strtolower($allowedSources));
    }

    private function hasNutritionNumber(string $text): bool
    {
        $number = '(?:\p{Nd}+(?:[.,]\p{Nd}+)*|(?:'.self::NUMBER_WORDS.')(?:\s+(?:'.self::NUMBER_WORDS.'))*)';

        return preg_match('/(?<![\p{L}\p{N}])'.$number.'\s*-?\s*(?:'.self::UNITS.')(?![\p{L}\p{N}])/u', $text) === 1
            || preg_match('/%\s*\p{Nd}|\p{Nd}\s*%/u', $text) === 1;
    }

    private function hasLink(string $text): bool
    {
        return preg_match('~(?:https?|ftp|file|mailto|data|javascript):|www\.|\]\(|<\s*a\b|href\s*=|(?<![\p{L}\p{N}])[\p{L}\p{N}-]+(?:\.[\p{L}\p{N}-]+)*\.(?:com|net|org|id|io|co|gov|edu|info|app|dev|ai|me|xyz|web|ly|ac|my|sg|uk|us|de)(?![\p{L}\p{N}])~u', $text) === 1;
    }

    /** Invented citations: [1], (Sumber: ...), doi, pmid. */
    private function hasCitationMarker(string $text): bool
    {
        return preg_match('/\[\s*\p{Nd}+\s*\]|(?<![\p{L}])(?:sumber|source|referensi|reference|rujukan|doi|pmid)\s*:|(?<![\p{L}])(?:doi|pmid)\s*\p{Nd}/u', $text) === 1;
    }

    private function hasForeignSource(string $text, string $allowed): bool
    {
        foreach (self::SOURCES as $name) {
            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($name, '/').'(?![\p{L}\p{N}])/u', $text) === 1
                && preg_match('/(?<![\p{L}\p{N}])'.preg_quote($name, '/').'(?![\p{L}\p{N}])/u', $allowed) !== 1) {
                return true;
            }
        }

        return false;
    }
}
