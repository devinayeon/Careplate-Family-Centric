<?php

namespace App\Services\Ai\Foods;

/**
 * Deterministic matcher from a curation row to a Foundation Foods record. A record matches only
 * when it contains every query term, its first description segment names the same food (so
 * "Sweet potatoes" never answers "potatoes"), and raw/cooked agree with the required state.
 * Several matches are accepted only as a "variant" when they differ in neither preparation
 * state nor numbers; the pick is then the fewest extra terms, then the lowest fdcId.
 */
class FoundationMatcher
{
    private const STOP = ['with', 'without', 'and', 'or', 'of', 'the', 'a', 'an', 'in', 'as', 'from', 'by'];

    /** Terms that change the preparation state or specification; candidates differing in these are ambiguous. */
    private const STATE = [
        'raw', 'cooked', 'ripe', 'overripe', 'unripe', 'frozen', 'dried', 'dry', 'canned', 'boiled', 'baked', 'fried',
        'roasted', 'steamed', 'grilled', 'braised', 'pasteurized', 'fresh', 'unheated', 'heated', 'sweetened',
        'unsweetened', 'nonfat', 'lowfat', 'reduced', 'skim', 'fat', 'lean', 'enriched', 'unenriched', 'salt', 'puffed',
    ];

    /**
     * @param  array<string, string>  $row  curation row
     * @param  list<array<string, mixed>>  $foods
     * @return array{status: 'matched', food: array<string, mixed>, kind: 'unique'|'variant', alternatives: list<array{fdc_id: string, description: string}>}|array{status: 'skipped', reason: string}
     */
    public function match(array $row, array $foods): array
    {
        $query = preg_split('/\bwithout\b/i', (string) $row['search_query_en'])[0];
        $terms = $this->tokens($query);
        $state = strtolower((string) ($row['required_state'] ?? ''));
        $hasRaw = preg_match('/\braw\b/', $state) === 1;
        $hasCooked = preg_match('/\bcooked\b/', $state) === 1;
        if ($hasRaw && ! $hasCooked) {
            $terms[] = 'raw';
        }
        if ($hasCooked && ! $hasRaw) {
            $terms[] = 'cooked';
        }
        $terms = array_values(array_unique($terms));
        $opposite = in_array('raw', $terms, true) ? 'cooked' : (in_array('cooked', $terms, true) ? 'raw' : null);

        $candidates = [];
        foreach ($foods as $food) {
            $description = (string) ($food['description'] ?? '');
            $words = $this->tokens($description);
            $head = $this->tokens(explode(',', $description)[0]);
            if ($head === [] || array_diff($terms, $words) !== [] || array_diff($head, $terms) !== []
                || ($opposite !== null && in_array($opposite, $words, true))) {
                continue;
            }
            $candidates[] = ['food' => $food, 'extra' => array_values(array_diff($words, $terms))];
        }
        if ($candidates === []) {
            return ['status' => 'skipped', 'reason' => 'no Foundation record has every query term in the required state ('.trim($query).')'];
        }

        usort($candidates, fn (array $a, array $b) => [count($a['extra']), (int) $a['food']['fdcId']] <=> [count($b['extra']), (int) $b['food']['fdcId']]);
        $best = $candidates[0];
        if (count($candidates) === 1) {
            return ['status' => 'matched', 'food' => $best['food'], 'kind' => 'unique', 'alternatives' => []];
        }

        $differing = array_unique(array_merge(...array_column($candidates, 'extra')));
        foreach ($differing as $word) {
            if (in_array($word, self::STATE, true) || preg_match('/^\d/', $word) === 1) {
                $ids = array_map(fn (array $c) => (string) $c['food']['fdcId'], $candidates);
                sort($ids, SORT_NATURAL);

                return ['status' => 'skipped', 'reason' => 'ambiguous: candidates differ in preparation state or numbers ('.implode(', ', $ids).')'];
            }
        }

        return [
            'status' => 'matched', 'food' => $best['food'], 'kind' => 'variant',
            'alternatives' => array_map(
                fn (array $c) => ['fdc_id' => (string) $c['food']['fdcId'], 'description' => (string) $c['food']['description']],
                array_slice($candidates, 1),
            ),
        ];
    }

    /** @return list<string> */
    private function tokens(string $text): array
    {
        preg_match_all('/[a-z0-9]+/', strtolower($text), $m);
        $out = [];
        foreach ($m[0] as $word) {
            if (! in_array($word, self::STOP, true)) {
                $out[] = $this->singular($word);
            }
        }

        return array_values(array_unique($out));
    }

    private function singular(string $word): string
    {
        return match (true) {
            str_ends_with($word, 'ies') => substr($word, 0, -3).'y',
            str_ends_with($word, 'oes') => substr($word, 0, -2),
            str_ends_with($word, 'ss'), str_ends_with($word, 'us') => $word,
            str_ends_with($word, 's') && strlen($word) > 3 => substr($word, 0, -1),
            default => $word,
        };
    }
}
