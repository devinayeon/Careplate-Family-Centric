<?php

namespace App\Services\MealLog;

use App\Models\Makanan;
use App\Models\PelacakanMakanan;
use App\Models\Pengguna;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Writes of the meal log ("Catatan Makanan"). The single place where total_kalori is computed for a user entry,
 * so the stored snapshot always equals energi x jumlah_porsi of the food at the time of logging.
 */
final class MealLogService
{
    /** The user's health profile row; created on the first log, never on a read. */
    public function penggunaFor(User $user): Pengguna
    {
        return Pengguna::query()->firstOrCreate(['user_id' => $user->id]);
    }

    /** @param array{makanan_id: int|string, waktu_makan: string, jumlah_porsi: int|float|string, tanggal_konsumsi: string} $data */
    public function record(User $user, array $data): PelacakanMakanan
    {
        $makanan = Makanan::query()->findOrFail((int) $data['makanan_id']);
        $porsi = (string) $data['jumlah_porsi'];

        return PelacakanMakanan::query()->create([
            'pengguna_id' => $this->penggunaFor($user)->id,
            'makanan_id' => $makanan->id,
            'tanggal_konsumsi' => $data['tanggal_konsumsi'],
            'waktu_konsumsi' => Carbon::now()->format('H:i:s'),
            'waktu_makan' => $data['waktu_makan'],
            'jumlah_porsi' => $porsi,
            'total_kalori' => PelacakanMakanan::hitungKalori($makanan, $porsi),
        ]);
    }

    /** @param array{makanan_id: int|string, waktu_makan: string, jumlah_porsi: int|float|string} $data */
    public function update(PelacakanMakanan $log, array $data): PelacakanMakanan
    {
        // The food may have been soft-deleted since it was logged; the log keeps pointing at it.
        $makanan = Makanan::withTrashed()->findOrFail((int) $data['makanan_id']);
        $porsi = (string) $data['jumlah_porsi'];

        $log->update([
            'makanan_id' => $makanan->id,
            'waktu_makan' => $data['waktu_makan'],
            'jumlah_porsi' => $porsi,
            'total_kalori' => PelacakanMakanan::hitungKalori($makanan, $porsi),
        ]);

        return $log;
    }

    /** True when the log belongs to this user (logs of other users are answered with 404). */
    public function owns(User $user, PelacakanMakanan $log): bool
    {
        $penggunaId = Pengguna::query()->where('user_id', $user->id)->value('id');

        return $penggunaId !== null && $log->pengguna_id === (int) $penggunaId;
    }

    /** The user's profile row read from the database (not a possibly stale relation cache); null before the first log. */
    public function currentPengguna(User $user): ?Pengguna
    {
        return Pengguna::query()->where('user_id', $user->id)->first();
    }
}
