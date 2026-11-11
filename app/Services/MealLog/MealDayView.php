<?php

namespace App\Services\MealLog;

use App\Models\Makanan;
use App\Models\PelacakanMakanan;
use App\Models\Pengguna;
use Carbon\CarbonImmutable;

/**
 * Read side of "Catatan Makanan" (Figma 03): one day grouped by meal time, the day's totals and a 7-day strip.
 * Calories come from the stored snapshot (total_kalori); the other nutrients from the food x portions, because only
 * calories are stored per log. Soft-deleted foods are still shown, so history never loses a row.
 */
final class MealDayView
{
    /** Daily limits shown in "Batas harian" (same references as the scan and food detail screens). */
    public const BATAS = ['gula' => 50, 'natrium' => 2000, 'lemak' => 67];

    private const HARI = ['Mg', 'Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb'];

    /** @return array<string, mixed> props for the page (see resources/js/pages/User/CatatanMakanan.vue) */
    public function build(?Pengguna $pengguna, CarbonImmutable $day): array
    {
        $logs = $pengguna === null ? collect() : PelacakanMakanan::query()
            ->where('pengguna_id', $pengguna->id)->whereDate('tanggal_konsumsi', $day->toDateString())
            ->orderBy('waktu_konsumsi')->orderBy('id')->get();
        // Foods are loaded with the deleted ones, so a log never loses its name after an admin removes the food.
        $foods = Makanan::withTrashed()->whereIn('id', $logs->pluck('makanan_id')->unique()->all())->get()->keyBy('id');

        $items = $logs->map(fn (PelacakanMakanan $log) => $this->item($log, $foods->get($log->makanan_id)));
        $groups = [];
        foreach (PelacakanMakanan::WAKTU_MAKAN as $key => $label) {
            $rows = $items->where('waktu_makan', $key)->values();
            $groups[] = ['key' => $key, 'label' => $label, 'total_kalori' => round((float) $rows->sum('total_kalori'), 2),
                'items' => $rows->map(fn (array $r) => array_diff_key($r, ['waktu_makan' => 1, 'gula' => 1, 'natrium' => 1]))->all()];
        }

        $target = $pengguna !== null && is_numeric($pengguna->kalori) && (float) $pengguna->kalori > 0 ? (float) $pengguna->kalori : null;

        return [
            'tanggal' => $day->toDateString(),
            'judulTanggal' => self::idFormat($day, 'l, j F'),
            'minggu' => $this->week($pengguna, $day),
            'waktuMakan' => $groups,
            'ringkasan' => ['kalori' => round((float) $items->sum('total_kalori'), 2), 'target_kalori' => $target]
                + collect(['protein', 'karbohidrat', 'lemak', 'gula', 'natrium'])->mapWithKeys(fn ($k) => [$k => round((float) $items->sum($k), 1)])->all(),
            'batas' => self::BATAS,
        ];
    }

    /** @return array<string, mixed> */
    private function item(PelacakanMakanan $log, ?Makanan $food): array
    {
        $porsi = (float) $log->jumlah_porsi;
        $per = fn (string $field) => $food === null ? 0.0 : round((float) $food->{$field} * $porsi, 1);

        return [
            'id' => $log->id,
            'makanan_id' => $log->makanan_id,
            'nama' => $food === null ? 'Makanan dihapus' : $food->nama,
            'foto_makanan_url' => $food?->foto_makanan_url,
            'jumlah_porsi' => $porsi,
            'takaran' => $food === null ? self::number($porsi).' porsi' : self::number((float) $food->kuantitas * $porsi).' '.$food->satuan,
            'jam' => str_replace(':', '.', substr((string) $log->waktu_konsumsi, 0, 5)),
            'total_kalori' => (float) $log->total_kalori,
            'protein' => $per('protein'), 'lemak' => $per('lemak'), 'karbohidrat' => $per('karbohidrat'),
            'gula' => $per('gula'), 'natrium' => $per('natrium'),
            'waktu_makan' => $log->waktuMakanEfektif(),
        ];
    }

    /** @return list<array{tanggal: string, label_hari: string, label_tanggal: string, total_kalori: float|null}> */
    private function week(?Pengguna $pengguna, CarbonImmutable $day): array
    {
        $from = $day->subDays(6);
        $totals = $pengguna === null ? collect() : PelacakanMakanan::query()->where('pengguna_id', $pengguna->id)
            // whereDate, not whereBetween: SQLite stores the date cast as "Y-m-d 00:00:00", which a plain string range misses.
            ->whereDate('tanggal_konsumsi', '>=', $from->toDateString())->whereDate('tanggal_konsumsi', '<=', $day->toDateString())
            ->get(['tanggal_konsumsi', 'total_kalori'])
            ->groupBy(fn (PelacakanMakanan $log) => $log->tanggal_konsumsi->toDateString())
            ->map(fn ($rows) => round((float) $rows->sum('total_kalori'), 2));

        $out = [];
        for ($d = $from; $d->lte($day); $d = $d->addDay()) {
            $out[] = ['tanggal' => $d->toDateString(), 'label_hari' => self::HARI[$d->dayOfWeek],
                'label_tanggal' => self::idFormat($d, 'j M'), 'total_kalori' => $totals->get($d->toDateString())];
        }

        return $out;
    }

    /** @return array{id: int, nama: string, takaran_saji: string, energi: float, protein: float, lemak: float, karbohidrat: float, foto_makanan_url: string|null} */
    public static function foodOption(Makanan $m): array
    {
        return [
            'id' => $m->id, 'nama' => $m->nama, 'takaran_saji' => self::number((float) $m->kuantitas).' '.$m->satuan,
            'energi' => (float) $m->energi, 'protein' => (float) $m->protein, 'lemak' => (float) $m->lemak,
            'karbohidrat' => (float) $m->karbohidrat, 'foto_makanan_url' => $m->foto_makanan_url,
        ];
    }

    /** Day names and months in Indonesian ("Senin, 2 November", "2 Nov") without touching the global locale. */
    private static function idFormat(CarbonImmutable $date, string $format): string
    {
        $local = $date->locale('id');

        return $local instanceof CarbonImmutable ? $local->translatedFormat($format) : $date->translatedFormat($format);
    }

    /** 100 -> "100", 0.5 -> "0,5", 1.25 -> "1,25" (Indonesian decimal comma, no trailing zeros). */
    private static function number(float $value): string
    {
        $text = number_format($value, 2, ',', '.');

        return str_contains($text, ',') ? rtrim(rtrim($text, '0'), ',') : $text;
    }
}
