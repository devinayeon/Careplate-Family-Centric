<?php

namespace App\Services\Ai\Chat;

use Normalizer;

/**
 * Checks what the model returned before it can reach a user. Two steps, no repair:
 *  - output(): the decoded JSON must match ai/schemas/provider-chat.json exactly (kind enum + text string,
 *    no other keys);
 *  - safe(): the text carries no nutrition number, link, citation or source name the server did not supply.
 * Product numbers and citations are built by the server; narration is education only. The guard fails closed:
 * a regular-expression error counts as unsafe, and a blocked narration only means the server template is shown.
 */
final class NarrationGuard
{
    public const KINDS = ['educational', 'clarification', 'referral'];

    public const MAX_CHARACTERS = 2000;

    /** @param  bool|null  $useIntl  null = use ext-intl when loaded; false forces the ASCII-fold fallback (tests) */
    public function __construct(private readonly ?bool $useIntl = null) {}

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
        $cased = $this->fold($text);
        if ($cased === null) {
            return false;
        }
        $lower = mb_strtolower($cased);

        return ! $this->hit(NarrationLexicon::nutritionNumber(), $lower)
            && ! $this->hit(NarrationLexicon::link(), $lower)
            && ! $this->hasCitation($cased)
            && ! $this->hasForeignSource($cased, $lower, mb_strtolower($allowedSources));
    }

    /**
     * NFKC (fullwidth digits and letters, ligatures) when ext-intl exists, else a conservative fold of the
     * fullwidth block; then format characters (zero-width space/joiner, soft hyphen, BOM) are removed so
     * "10<zwsp>g" reads as "10g", and exotic spaces become plain spaces. Null (invalid UTF-8) = unsafe.
     */
    private function fold(string $text): ?string
    {
        $useIntl = ($this->useIntl ?? true) && class_exists(Normalizer::class);
        $folded = $useIntl ? (Normalizer::normalize($text, Normalizer::FORM_KC) ?: $text) : $this->asciiFold($text);
        $folded = preg_replace(['/\p{Cf}+/u', '/[\p{Z}\p{Cc}]+/u'], ['', ' '], $folded);

        return $folded;
    }

    /** U+FF01..U+FF5E (fullwidth ASCII) to ASCII, and the ideographic space. */
    private function asciiFold(string $text): string
    {
        $text = preg_replace_callback(
            '/[\x{FF01}-\x{FF5E}]/u',
            fn (array $m) => mb_chr(mb_ord($m[0]) - 0xFEE0),
            $text,
        );

        return str_replace("\u{3000}", ' ', $text ?? '');
    }

    /** Fail closed: a pattern that errors (false) counts as a hit. */
    private function hit(string $pattern, string $text): bool
    {
        return preg_match($pattern, $text) !== 0;
    }

    private function hasCitation(string $cased): bool
    {
        foreach (NarrationLexicon::citations() as $pattern) {
            if ($this->hit($pattern, $cased)) {
                return true;
            }
        }

        // "Sumber data:" is a citation label; "sumber protein nabati:" is an everyday phrase.
        if (preg_match_all(NarrationLexicon::sourceLabel(), $cased, $labels) === false) {
            return true;
        }
        foreach ($labels[1] as $between) {
            if (preg_match(NarrationLexicon::nutrientIdiom(), $between) !== 1) {
                return true;
            }
        }

        return false;
    }

    /** Long names match case-insensitively; short ones that are also everyday words ("nih", "aha") only in UPPER case. */
    private function hasForeignSource(string $cased, string $lower, string $allowed): bool
    {
        foreach (NarrationLexicon::sources() as $name) {
            $strict = in_array($name, NarrationLexicon::acronyms(), true) || strlen($name) <= 3;
            $word = '(?<![\p{L}\p{N}])'.preg_quote($strict ? strtoupper($name) : $name, '/').'(?![\p{L}\p{N}])';
            if ($this->hit('/'.$word.'/u', $strict ? $cased : $lower)
                && preg_match('/(?<![\p{L}\p{N}])'.preg_quote($name, '/').'(?![\p{L}\p{N}])/u', $allowed) !== 1) {
                return true;
            }
        }

        return false;
    }
}
