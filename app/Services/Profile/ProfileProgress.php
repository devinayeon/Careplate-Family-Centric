<?php

namespace App\Services\Profile;

use App\Models\Bmi;
use App\Models\KebutuhanKalori;
use App\Models\Makanan;
use App\Models\PelacakanMakanan;
use App\Models\Pengguna;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What the profile page (Figma 11.1) shows besides the identity: the streak of consecutive logging days and the
 * badges ("Lencana"). Only badges that stored data can prove are offered. "Detektif Label", "Pembaca Label"
 * (scans are never stored), "Penanya Gizi" (chats are purged after 7 days), "Konsultasi Pertama" and "Pembaca Setia"
 * (no table records them) are not shown: a badge that can never unlock would mislead.
 */
final class ProfileProgress
{
    private const BULAN = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** Streak targets of "Rajin Mencatat". */
    private const TARGET_RINGAN = 7;

    private const TARGET_BERAT = 30;

    /**
     * @return array{streak: array{saat_ini: int, terbaik: int}, lencana: array{terbuka: int, total: int, daftar: list<array<string, mixed>>}}
     */
    public function forUser(User $user, ?Pengguna $pengguna, CarbonImmutable $today): array
    {
        $days = $pengguna === null ? collect() : $this->loggingDays($pengguna);
        $streak = $this->streak($days, $today);

        $badges = [
            $this->simple('langkah_pertama', 'Langkah Pertama', 'Catat satu makanan', $days->first()),
            $this->simple('kenal_tubuhku', 'Kenal Tubuhku', 'Hitung BMI', $pengguna === null ? null : Bmi::query()->where('pengguna_id', $pengguna->id)->min('created_at')),
            $this->simple('tahu_kebutuhanku', 'Tahu Kebutuhanku', 'Hitung kebutuhan kalori', $pengguna === null ? null : KebutuhanKalori::query()->where('pengguna_id', $pengguna->id)->min('created_at')),
            // The join date is not stored: unlocked without a date.
            $this->simple('satu_meja', 'Satu Meja', 'Gabung grup keluarga', $pengguna?->keluarga_id !== null ? true : null),
            $this->streakBadge('rajin_mencatat_7', 'Rajin Mencatat 7', 'Catat 7 hari berturut-turut', self::TARGET_RINGAN, $days, $streak['terbaik']),
            $this->streakBadge('rajin_mencatat_30', 'Rajin Mencatat 30', 'Catat 30 hari berturut-turut', self::TARGET_BERAT, $days, $streak['terbaik']),
            $this->simple('penyumbang_data', 'Penyumbang Data', 'Satu makanan yang kamu ajukan terverifikasi', Makanan::query()
                ->where('pengaju_id', $user->id)->where('status', 'terverifikasi')->min('diverifikasi_pada')),
        ];

        return [
            'streak' => $streak,
            'lencana' => ['terbuka' => count(array_filter($badges, fn (array $b) => $b['terbuka'])), 'total' => count($badges), 'daftar' => $badges],
        ];
    }

    /** @return Collection<int, CarbonImmutable> distinct logging days, oldest first */
    private function loggingDays(Pengguna $pengguna): Collection
    {
        return PelacakanMakanan::query()->where('pengguna_id', $pengguna->id)->get(['tanggal_konsumsi'])
            ->map(fn (PelacakanMakanan $log) => CarbonImmutable::parse($log->tanggal_konsumsi->toDateString()))
            ->unique(fn (CarbonImmutable $d) => $d->toDateString())->sortBy(fn (CarbonImmutable $d) => $d->getTimestamp())->values();
    }

    /**
     * Current = consecutive days ending today, or ending yesterday (today is still open, so the streak is not lost yet).
     * Best = the longest run ever.
     *
     * @param  Collection<int, CarbonImmutable>  $days
     * @return array{saat_ini: int, terbaik: int}
     */
    private function streak(Collection $days, CarbonImmutable $today): array
    {
        $best = 0;
        $run = 0;
        $previous = null;
        foreach ($days as $day) {
            $run = $previous !== null && $previous->addDay()->isSameDay($day) ? $run + 1 : 1;
            $best = max($best, $run);
            $previous = $day;
        }

        $current = 0;
        if ($previous !== null && ($previous->isSameDay($today) || $previous->addDay()->isSameDay($today))) {
            $current = $run;
        }

        return ['saat_ini' => $current, 'terbaik' => $best];
    }

    /** @return array<string, mixed> */
    private function simple(string $key, string $title, string $description, mixed $when): array
    {
        return [
            'key' => $key, 'judul' => $title, 'deskripsi' => $description, 'terbuka' => $when !== null,
            'tanggal' => $when === null || $when === true ? null : $this->label($when), 'progres' => null,
        ];
    }

    /**
     * @param  Collection<int, CarbonImmutable>  $days
     * @return array<string, mixed>
     */
    private function streakBadge(string $key, string $title, string $description, int $target, Collection $days, int $best): array
    {
        $reached = $best >= $target ? $this->dayTargetReached($days, $target) : null;

        return [
            'key' => $key, 'judul' => $title, 'deskripsi' => $description, 'terbuka' => $reached !== null,
            'tanggal' => $reached === null ? null : $this->label($reached),
            'progres' => $reached === null ? ['sekarang' => min($best, $target), 'target' => $target, 'satuan' => 'hari'] : null,
        ];
    }

    /**
     * The day a run first reached `target` consecutive days.
     *
     * @param  Collection<int, CarbonImmutable>  $days
     */
    private function dayTargetReached(Collection $days, int $target): ?CarbonImmutable
    {
        $run = 0;
        $previous = null;
        foreach ($days as $day) {
            $run = $previous !== null && $previous->addDay()->isSameDay($day) ? $run + 1 : 1;
            if ($run >= $target) {
                return $day;
            }
            $previous = $day;
        }

        return null;
    }

    private function label(mixed $date): string
    {
        $d = CarbonImmutable::parse($date);

        return $d->day.' '.self::BULAN[$d->month];
    }
}
