<?php

namespace App\Services\Ai\Foods;

use App\Models\Food;
use App\Models\Makanan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * For the admin screens: is a verified `makanan` row really usable by the AI chat, and if not, what to fix.
 * One query for the whole page (the published AI foods of these ids), no per-row lookups.
 */
final class AiFoodStatus
{
    /**
     * @param  Collection<int, Makanan>  $rows
     * @return array<int, array{state: string, label: string}|null> keyed by makanan id; null = not verified, nothing to say
     */
    public static function forMany(Collection $rows): array
    {
        $published = Food::verified()->where('source_provider', MakananSource::PROVIDER)
            ->whereIn('source_id', $rows->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->pluck('source_id')->flip();

        return $rows->mapWithKeys(fn (Makanan $m) => [$m->id => self::one($m, $published->has((string) $m->id))])->all();
    }

    /**
     * Whole-catalog state for the dashboard panel. "failed" = verified and eligible but not published (the sync was off
     * or errored): the one thing a retry can fix. "needs_weight" = verified, but the admin must add a weight first.
     *
     * @return array{ready: int, needs_weight: int, failed: int, failed_names: list<string>, last_sync: ?Carbon}
     */
    public static function summary(): array
    {
        $published = Food::verified()->where('source_provider', MakananSource::PROVIDER)->pluck('source_id')->flip();
        $out = ['ready' => 0, 'needs_weight' => 0, 'failed' => 0, 'failed_names' => []];

        Makanan::query()->where('status', MakananSource::VERIFIED_STATUS)->orderBy('id')->each(function (Makanan $m) use ($published, &$out): void {
            $reason = MakananSource::ineligibleReason($m);
            if ($reason === 'unit_not_gram') {
                $out['needs_weight']++;
            } elseif ($reason === null && $published->has((string) $m->id)) {
                $out['ready']++;
            } elseif ($reason === null) {
                $out['failed']++;
                if (count($out['failed_names']) < 3) {
                    $out['failed_names'][] = (string) $m->nama;
                }
            }
        });

        $last = Food::query()->where('source_provider', MakananSource::PROVIDER)->max('updated_at');

        return $out + ['last_sync' => $last === null ? null : Carbon::parse($last)];
    }

    /** @return array{state: string, label: string}|null */
    private static function one(Makanan $m, bool $published): ?array
    {
        if ($m->status !== MakananSource::VERIFIED_STATUS || $m->trashed()) {
            return null;
        }
        if (($reason = MakananSource::ineligibleReason($m)) !== null) {
            return ['state' => 'blocked', 'label' => match ($reason) {
                'unit_not_gram' => "Belum dipakai Chat Gizi: isi berat per {$m->satuan} (gram)",
                'quantity_invalid' => 'Belum dipakai Chat Gizi: takaran saji harus lebih dari 0',
                default => 'Belum dipakai Chat Gizi',
            }];
        }

        return $published
            ? ['state' => 'ready', 'label' => 'Dipakai Chat Gizi']
            : ['state' => 'blocked', 'label' => 'Belum dipakai Chat Gizi: jalankan php artisan ai:sync-makanan'];
    }
}
