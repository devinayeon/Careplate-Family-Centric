<?php

namespace App\Services\Ai\Foods;

use App\Actions\Foods\FoodSearchText;
use App\Exceptions\Ai\AiException;
use App\Models\Food;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PDOException;

/**
 * Read-only lookup of verified foods. Three entry points, no model call, no writes:
 *  - find(): one verified food by explicit id;
 *  - resolve(): food words taken from a message, matched only against names and aliases (the whitelist);
 *  - list(): GET /ai/v1/foods typeahead.
 * Text is folded with FoodSearchText::normalise(), the same function that built `search_text`.
 * A database failure is KNOWLEDGE_UNAVAILABLE 503, never an empty result.
 */
class FoodSearch
{
    public const MAX_QUERY = 200;

    public const MAX_CANDIDATES = 5;

    private const MAX_TOKENS = 12;

    private const PREFILTER_ROWS = 200;

    /** Escape character used with `LIKE ? ESCAPE '!'` (portable across SQLite, MySQL and PostgreSQL). */
    private const ESCAPE = '!';

    private const LIKE = "search_text LIKE ? ESCAPE '!'";

    public function find(string $foodId): ?Food
    {
        if (! Str::isUuid($foodId)) {
            return null;
        }

        return $this->guard(fn () => Food::query()->verified()->whereKey($foodId)->first());
    }

    /**
     * Resolve food phrases (content runs from IntentParser) to verified foods.
     * Every phrase must equal a name/alias, or be a contiguous part of one; an exact alias beats partial matches.
     * One food => found; several (or several foods across phrases) => ambiguous; a phrase nobody knows => no_match.
     * The SQL prefilter needs ALL tokens of a run, and fetches one row more than its cap: if the cap is exceeded
     * the catalog is too broad for this phrase and the answer is ambiguous, never found or no_match.
     *
     * @param  list<string>  $phrases
     */
    public function resolve(array $phrases): FoodMatch
    {
        $runs = [];
        foreach ($phrases as $phrase) {
            $tokens = $this->tokens(mb_substr($phrase, 0, self::MAX_QUERY));
            if ($tokens !== []) {
                $runs[] = $tokens;
            }
        }
        if ($runs === []) {
            return new FoodMatch(FoodMatch::NO_MATCH);
        }

        $chosen = [];
        $pool = [];
        foreach ($runs as $run) {
            $rows = $this->prefilter($run);
            if ($rows === null) {
                return new FoodMatch(FoodMatch::NO_MATCH);
            }
            $truncated = $rows->count() > self::PREFILTER_ROWS;
            $rows = $rows->take(self::PREFILTER_ROWS);
            $hits = $this->hits($rows, $run);
            if ($truncated) {
                $show = $hits === [] ? $rows : $rows->filter(fn (Food $f) => isset($hits[$f->getKey()]));

                return new FoodMatch(FoodMatch::AMBIGUOUS, array_values($show->take(self::MAX_CANDIDATES)->all()));
            }
            if ($hits === []) {
                return new FoodMatch(FoodMatch::NO_MATCH);
            }
            $chosen += $hits;
            foreach ($rows as $row) {
                $pool[$row->getKey()] = $row;
            }
        }
        $candidates = array_values(collect($pool)->filter(fn (Food $f, $id) => isset($chosen[$id]))
            ->sortBy([['name', 'asc'], ['id', 'asc']])->take(self::MAX_CANDIDATES)->all());

        return new FoodMatch(count($chosen) === 1 ? FoodMatch::FOUND : FoodMatch::AMBIGUOUS, $candidates);
    }

    /**
     * Verified rows whose search_text contains every token of the run, name then id, one row over the cap.
     * Null when the run has no token long enough to filter on.
     *
     * @param  list<string>  $run
     * @return Collection<int, Food>|null
     */
    private function prefilter(array $run): ?Collection
    {
        $probe = array_slice(array_values(array_unique(array_filter($run, fn (string $t) => mb_strlen($t) >= 2))), 0, self::MAX_TOKENS);
        if ($probe === []) {
            return null;
        }

        return $this->guard(fn () => Food::query()->verified()
            ->where(function ($q) use ($probe): void {
                foreach ($probe as $token) {
                    $q->whereRaw(self::LIKE, ['%'.self::escapeLike($token).'%']);
                }
            })
            ->orderBy('name')->orderBy('id')->limit(self::PREFILTER_ROWS + 1)->get());
    }

    /**
     * Verified foods whose searchable text contains the folded query; empty query lists the first by name, id.
     *
     * @return Collection<int, Food>
     */
    public function list(string $q, int $limit = self::MAX_CANDIDATES): Collection
    {
        $q = trim($q);
        if (mb_strlen($q) > self::MAX_QUERY) {
            throw AiException::for('INVALID_INPUT');
        }
        $limit = max(1, min(self::MAX_CANDIDATES, $limit));
        $query = Food::query()->verified();
        if ($q !== '') {
            $text = FoodSearchText::normalise($q);
            if ($text === '') {
                return new Collection;
            }
            $query->whereRaw(self::LIKE, ['%'.self::escapeLike($text).'%']);
        }
        if ($q === '') {
            return $this->guard(fn () => $query->orderBy('name')->orderBy('id')->limit($limit)->get());
        }

        // MySQL's ci/accent-insensitive collation makes LIKE a superset ('tahu' matches 'táhu'): the SQL result is
        // only a prefilter and PHP keeps the rows that really contain the folded query, as on SQLite.
        $rows = $this->guard(fn () => $query->orderBy('name')->orderBy('id')->limit(self::PREFILTER_ROWS)->get());

        return $rows->filter(fn (Food $f) => str_contains((string) $f->search_text, $text))->take($limit)->values();
    }

    /** Escape LIKE wildcards (and the escape character) so user text is only ever a literal. */
    public static function escapeLike(string $text): string
    {
        return str_replace([self::ESCAPE, '%', '_'], [self::ESCAPE.self::ESCAPE, self::ESCAPE.'%', self::ESCAPE.'_'], $text);
    }

    /** @return list<string> */
    private function tokens(string $text): array
    {
        $folded = FoodSearchText::normalise($text);

        return $folded === '' ? [] : explode(' ', $folded);
    }

    /**
     * Foods hit by one run: exact alias matches if any, else partial ones. Keyed by food id.
     *
     * @param  Collection<int, Food>  $rows
     * @param  list<string>  $run
     * @return array<string, true>
     */
    private function hits(Collection $rows, array $run): array
    {
        $exact = $partial = [];
        foreach ($rows as $food) {
            foreach ($this->names($food) as $alias) {
                if ($alias === $run) {
                    $exact[$food->getKey()] = true;
                } elseif ($this->contains($alias, $run)) {
                    $partial[$food->getKey()] = true;
                }
            }
        }

        return $exact ?: $partial;
    }

    /** @return list<list<string>> token lists of the name and every alias */
    private function names(Food $food): array
    {
        $names = [];
        $aliases = $food->getAttribute('aliases');
        foreach ([$food->name, ...(is_array($aliases) ? array_filter($aliases, 'is_string') : [])] as $name) {
            $tokens = $this->tokens($name);
            if ($tokens !== []) {
                $names[] = $tokens;
            }
        }

        return $names;
    }

    /**
     * @param  list<string>  $haystack
     * @param  list<string>  $needle
     */
    private function contains(array $haystack, array $needle): bool
    {
        $n = count($needle);
        for ($i = 0; $i + $n <= count($haystack); $i++) {
            if (array_slice($haystack, $i, $n) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $query
     * @return T
     */
    private function guard(Closure $query): mixed
    {
        try {
            return $query();
        } catch (QueryException|PDOException $e) {
            // Metadata only: the SQL and bindings are never logged or shown.
            Log::warning('ai.foods', ['feature' => 'foods', 'status' => 'KNOWLEDGE_UNAVAILABLE']);

            throw AiException::for('KNOWLEDGE_UNAVAILABLE', previous: $e);
        }
    }
}
