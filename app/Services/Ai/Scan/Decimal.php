<?php

namespace App\Services\Ai\Scan;

/**
 * Small BCMath helpers. All nutrition math runs on decimal strings; floats only
 * appear at the JSON boundary (public numbers) and when reading provider numbers.
 */
final class Decimal
{
    /** Working precision for intermediate BCMath results. */
    public const SCALE = 8;

    /** Decimal string of a finite, non-negative provider number, without float noise. */
    public static function fromNumber(int|float $n): string
    {
        $s = is_int($n) ? (string) $n : self::trim(sprintf('%.10F', $n));

        return $s === '' || $s === '-0' ? '0' : $s;
    }

    /** Round half up (non-negative values) to $scale decimals, trailing zeros removed. */
    public static function round(string $x, int $scale): string
    {
        $r = self::trim(bcadd(self::num($x), self::num('0.'.str_repeat('0', $scale).'5'), $scale));

        return $r === '' ? '0' : $r;
    }

    /** Public JSON number: int when integral, else float, rounded to $scale decimals. */
    public static function toNumber(string $x, int $scale = 4): int|float
    {
        $r = self::round($x, $scale);

        // Integral strings that do not fit an int must not saturate to PHP_INT_MAX.
        return str_contains($r, '.') || strlen($r) > 18 ? (float) $r : (int) $r;
    }

    /** @return numeric-string */
    public static function num(string $x): string
    {
        if (! is_numeric($x)) {
            throw new \InvalidArgumentException('Not a decimal string.');
        }

        return $x;
    }

    public static function mul(string $a, string $b): string
    {
        return bcmul(self::num($a), self::num($b), self::SCALE);
    }

    public static function div(string $a, string $b): string
    {
        return bcdiv(self::num($a), self::num($b), self::SCALE);
    }

    /** Exact comparison at working precision: -1, 0 or 1. */
    public static function cmp(string $a, string $b): int
    {
        return bccomp(self::num($a), self::num($b), self::SCALE);
    }

    public static function trim(string $x): string
    {
        return str_contains($x, '.') ? rtrim(rtrim($x, '0'), '.') : $x;
    }

    /** Indonesian display: decimal comma, up to 2 decimals (22,5 / 11,94 / 24). */
    public static function display(string $x): string
    {
        return str_replace('.', ',', self::round($x, 2));
    }
}
