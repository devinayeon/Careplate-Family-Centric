<?php

namespace App\Services\Ai\Foods;

use App\Models\Makanan;

/**
 * The team's `makanan` master table seen as a source of AI foods.
 *
 * `makanan` stores nutrients PER PORTION (`kuantitas` + `satuan`); AI foods are per 100 g only. The serving
 * weighs `kuantitas` g for a gram unit, or `kuantitas` x `berat_per_satuan_gram` for any other unit (porsi,
 * butir, ml...) when the admin gave that weight. Without a usable weight the row is not eligible: guessing a
 * weight would invent numbers. Eligibility, the per-100 g conversion and the content hash live here so the
 * publisher (SyncMakananToFoods) and the verifier (ReviewCandidate) agree.
 */
final class MakananSource
{
    public const PROVIDER = 'CAREPLATE_MAKANAN';

    public const VERIFIED_STATUS = 'terverifikasi';

    private const GRAM_UNITS = ['g', 'gram'];

    /** Hex characters of the content hash that prefix the snapshot version. */
    public const VERSION_PREFIX_LENGTH = 12;

    /** @return string|null why the row cannot be published as an AI food (null = eligible) */
    public static function ineligibleReason(Makanan $m): ?string
    {
        if ($m->trashed()) {
            return 'deleted';
        }
        if ($m->status !== self::VERIFIED_STATUS) {
            return 'not_verified';
        }
        if (trim((string) $m->nama) === '') {
            return 'name_empty';
        }
        if (! self::isGram($m) && self::unitWeight($m) === null) {
            return 'unit_not_gram';
        }
        if (! is_numeric($m->kuantitas) || bccomp((string) $m->kuantitas, '0', 4) <= 0) {
            return 'quantity_invalid';
        }

        return null;
    }

    /** Grams of the whole serving as a decimal string (call only on an eligible row). */
    public static function servingGrams(Makanan $m): string
    {
        $quantity = (string) $m->kuantitas;

        return self::isGram($m) ? $quantity : bcmul($quantity, (string) self::unitWeight($m), 8);
    }

    /**
     * Nutrients per 100 g as 4-decimal strings. `gula` and `natrium` were added later with a default of 0, so a stored 0
     * cannot be told from "unknown": it stays null (unknown is never 0).
     *
     * @return array{energy_kcal: string, protein_g: string, fat_g: string, carbohydrate_g: string, sodium_mg: ?string, sugars_total_g: ?string}
     */
    public static function per100g(Makanan $m): array
    {
        $quantity = self::servingGrams($m);

        return [
            'energy_kcal' => self::scale($m->energi, $quantity),
            'protein_g' => self::scale($m->protein, $quantity),
            'fat_g' => self::scale($m->lemak, $quantity),
            'carbohydrate_g' => self::scale($m->karbohidrat, $quantity),
            'sodium_mg' => self::optional($m->natrium, $quantity),
            'sugars_total_g' => self::optional($m->gula, $quantity),
        ];
    }

    /** Hash of everything the AI publishes about the row: a change of any of it is a new version. */
    public static function contentHash(Makanan $m): string
    {
        return hash('sha256', (string) json_encode([
            'name' => trim((string) $m->nama),
            'per100g' => self::per100g($m),
            'source' => trim((string) $m->sumber),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * Counterpart of SnapshotManifest::sha256For for this provider: the hash of the CURRENT row, only when the row is
     * still eligible and the version was built from that exact content. Null otherwise (the approval is refused).
     */
    public static function sha256For(string $sourceId, string $version): ?string
    {
        if (ctype_digit($sourceId) === false) {
            return null;
        }
        $row = Makanan::query()->find((int) $sourceId);
        if ($row === null || self::ineligibleReason($row) !== null) {
            return null;
        }
        $hash = self::contentHash($row);

        return str_starts_with($version, substr($hash, 0, self::VERSION_PREFIX_LENGTH).'-') ? $hash : null;
    }

    private static function isGram(Makanan $m): bool
    {
        return in_array(mb_strtolower(trim((string) $m->satuan)), self::GRAM_UNITS, true);
    }

    /** Grams per one unit, or null when missing, not numeric or not above zero. */
    private static function unitWeight(Makanan $m): ?string
    {
        $weight = $m->berat_per_satuan_gram;

        return is_numeric($weight) && bccomp((string) $weight, '0', 4) > 0 ? (string) $weight : null;
    }

    private static function optional(mixed $value, string $quantity): ?string
    {
        if ($value === null || bccomp((string) $value, '0', 4) === 0) {
            return null;
        }

        return self::scale($value, $quantity);
    }

    /** value * 100 / quantity, rounded half up to 4 decimals (BCMath; never a float). */
    private static function scale(mixed $value, string $quantity): string
    {
        $raw = bcdiv(bcmul(self::decimal($value), '100', 8), $quantity, 8);

        return bcadd($raw, '0.00005', 4);
    }

    private static function decimal(mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : '0';

        return is_numeric($text) && bccomp($text, '0', 8) >= 0 ? $text : '0';
    }
}
