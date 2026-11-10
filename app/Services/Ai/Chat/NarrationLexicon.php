<?php

namespace App\Services\Ai\Chat;

/**
 * Word lists and regular expressions behind NarrationGuard. Everything here errs towards blocking: a blocked
 * narration only means the server template is shown instead. Patterns run on NFKC-folded text without
 * format characters; `lower` ones expect lower-case input, `cased` ones the original case.
 */
final class NarrationLexicon
{
    /** Indonesian and English number words, including vague multiples (puluhan, ratusan, belasan). */
    private const NUMBER_WORDS = [
        'seratus', 'seribu', 'sejuta', 'sepertiga', 'seperempat', 'setengah', 'sepuluh', 'sebelas', 'sedozen',
        'puluhan', 'ratusan', 'ribuan', 'belasan', 'jutaan', 'puluh', 'ratus', 'ribu', 'juta', 'belas', 'miliar',
        'nol', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'lusin',
        'zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
        'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen', 'twenty', 'thirty',
        'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety', 'hundred', 'thousand', 'million', 'billion',
        'half', 'quarter', 'dozens', 'hundreds', 'thousands', 'dozen',
    ];

    private const UNITS = [
        'kilokalori', 'kilokalorie', 'kilojoule', 'kilogram', 'miligram', 'mikrogram', 'mililiter', 'calories',
        'calorie', 'kalori', 'ounces', 'ounce', 'pounds', 'pound', 'grams', 'gram', 'joule', 'liter', 'litre',
        'persen', 'percent', 'kcal', 'kkal', 'kilo', 'mcg', 'ons', 'kal', 'cal', 'oz', 'lbs', 'lb', 'mg', 'kg',
        'gr', 'kj', 'ml', 'cc', 'iu', 'sdm', 'sdt', 'µg', 'μg', 'ug', 'g', '%',
    ];

    /** Authorities, publishers and institutions; allowed only when the server's own source text names them. */
    private const SOURCES = [
        'usda', 'fooddata', 'fdc', 'tkpi', 'kemenkes', 'kementerian kesehatan', 'bpom', 'who', 'fao', 'fda', 'nih',
        'cdc', 'efsa', 'nhs', 'aha', 'pubmed', 'wikipedia', 'mayo clinic', 'halodoc', 'alodokter', 'healthline',
        'webmd', 'cochrane', 'idai', 'persagi', 'nutrition facts', 'cnn', 'kompas', 'detik', 'harvard', 'stanford',
        'oxford', 'cambridge', 'lancet', 'universitas', 'university',
    ];

    /** Short authority names that are also everyday words ("nih", "aha"): only the UPPER-case spelling counts. */
    private const ACRONYMS = ['nih', 'aha', 'who', 'cdc', 'nhs', 'fda', 'fao', 'fdc', 'usda'];

    /** Nouns that name a body of evidence. */
    private const EVIDENCE = 'jurnal|studi|penelitian|riset|survei|study|studies|research|journal|trial|meta-analisis|meta-analysis';

    /** Words after "sumber ...:" that make it an everyday idiom ("sumber protein nabati:"), not a citation. */
    private const NUTRIENT_SOURCE = 'protein|vitamin|mineral|serat|lemak|karbohidrat|energi|kalsium|zat besi|nabati|hewani|natrium|gula';

    /** @return string pattern: a number (digits or number words) followed by a nutrition unit */
    public static function nutritionNumber(): string
    {
        $words = implode('|', self::NUMBER_WORDS);
        $number = '(?:\p{Nd}+(?:[.,]\p{Nd}+)*|(?:'.$words.')(?:[\s-]+(?:'.$words.'))*)';
        $units = implode('|', array_map(fn (string $u) => preg_quote($u, '/'), self::UNITS));

        return '/(?<![\p{L}\p{N}])'.$number.'\s*-?\s*(?:'.$units.')(?![\p{L}\p{N}])|%\s*\p{Nd}|\p{Nd}\s*%/u';
    }

    public static function link(): string
    {
        return '~(?:https?|ftp|file|mailto|data|javascript):|www\.|\]\(|<\s*a\b|href\s*=|(?<![\p{L}\p{N}])[\p{L}\p{N}-]+(?:\.[\p{L}\p{N}-]+)*\.(?:com|net|org|id|io|co|gov|edu|info|app|dev|ai|me|xyz|web|ly|ac|my|sg|uk|us|de)(?![\p{L}\p{N}])~u';
    }

    /**
     * Invented citations, on the ORIGINAL-case text (capital letters matter): "[1]", "Sumber:", "et al.",
     * "(Smith, 2019)", "Menurut Harvard", "penelitian ... 2019", "jurnal The Lancet". Keywords use (?i:) groups
     * so that \p{Lu} stays case-sensitive.
     *
     * @return list<string> patterns
     */
    public static function citations(): array
    {
        $evidence = self::EVIDENCE;

        return [
            '/\[\s*\p{Nd}+\s*\]/u',
            '/(?<![\p{L}])(?i:doi|pmid)\s*[:\p{Nd}]/u',
            '/(?<![\p{L}])(?i:et)\s+(?i:al)(?![\p{L}])/u',
            '/\([^()]{0,60}(?<![\p{N}])(?:19|20)\p{Nd}{2}[a-z]?(?![\p{N}])[^()]{0,30}\)/u',
            '/(?<![\p{L}])(?i:menurut)\s+(?!Anda\b|Saya\b|Kita\b|Kami\b|Kamu\b)\p{Lu}/u',
            '/(?<![\p{L}])(?i:'.$evidence.')(?![\p{L}])[^.!?\n]{0,40}(?<![\p{N}])(?:19|20)\p{Nd}{2}(?![\p{N}])/u',
            '/(?<![\p{N}])(?:19|20)\p{Nd}{2}(?![\p{N}])[^.!?\n]{0,40}(?<![\p{L}])(?i:'.$evidence.')(?![\p{L}])/u',
            '/(?<![\p{L}])(?i:'.$evidence.')(?:\s+\p{Ll}+){0,3}\s+(?:(?i:oleh)\s+)?\p{Lu}\p{L}+/u',
            '/(?<![\p{L}])(?i:sumber|source|referensi|reference|rujukan|daftar pustaka|bibliography)\s*:/u',
        ];
    }

    /** "Sumber data:" style label; capture 1 is the words between the keyword and the colon. */
    public static function sourceLabel(): string
    {
        return '/(?<![\p{L}])(?i:sumber|source|referensi|reference|rujukan)\s+([\p{L}\p{N}\s]{1,15}):/u';
    }

    /** Everyday nutrition idiom inside a label ("sumber protein nabati:"): not a citation. */
    public static function nutrientIdiom(): string
    {
        return '/(?<![\p{L}])(?:'.self::NUTRIENT_SOURCE.')(?![\p{L}])/iu';
    }

    /** @return list<string> */
    public static function sources(): array
    {
        return self::SOURCES;
    }

    /** @return list<string> */
    public static function acronyms(): array
    {
        return self::ACRONYMS;
    }
}
