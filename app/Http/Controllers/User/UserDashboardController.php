<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\PelacakanMakanan;
use App\Models\Pengguna;
use App\Services\Profile\ProfileProgress;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserDashboardController extends Controller
{
    /**
     * Tampilkan halaman Beranda Pengguna (Customer).
     */
    public function index(Request $request, ProfileProgress $progress): Response
    {
        $user = $request->user();
        $today = Carbon::today();

        // Pastikan pengguna memiliki record profil kesehatan
        $pengguna = $user?->pengguna;
        if ($user && ! $pengguna) {
            $pengguna = $user->pengguna()->firstOrCreate([]);
        }

        // Kalori terkonsumsi hari ini (gunakan Collection agar aman dari pemanggilan method Eloquent)
        $catatanMakananHariIni = collect();
        $kaloriTerkonsumsi = 0.00;

        if ($pengguna) {
            $catatanMakananHariIni = PelacakanMakanan::with('makanan')
                ->where('pengguna_id', $pengguna->id)
                ->whereDate('tanggal_konsumsi', $today)
                ->get();

            $kaloriTerkonsumsi = (float) $catatanMakananHariIni->sum('total_kalori');
        }

        $targetKalori = (float) ($pengguna?->kalori ?? 0.00);

        // Misi harian: judged by meal time (sarapan, makan_siang, makan_malam), not by the number of rows.
        $waktuMakanHariIni = $catatanMakananHariIni->map(fn (PelacakanMakanan $c) => $c->waktuMakanEfektif())->unique();
        $waktuUtama = $waktuMakanHariIni->intersect(['sarapan', 'makan_siang', 'makan_malam']);

        $misiList = [
            [
                'id' => 1,
                'title' => 'Catat sarapan',
                'action_label' => 'Catat',
                'route' => 'user.meals',
                'completed' => $waktuMakanHariIni->contains('sarapan'),
            ],
            [
                'id' => 2,
                'title' => 'Catat tiga waktu makan',
                'action_label' => 'Lihat catatan',
                'route' => 'user.meals',
                'completed' => $waktuUtama->count() >= 3,
            ],
            [
                'id' => 3,
                'title' => 'Pindai satu label kemasan',
                'action_label' => 'Pindai',
                'route' => 'user.scan',
                'completed' => false,
            ],
        ];

        $completedMissionsCount = count(array_filter($misiList, fn ($m) => $m['completed']));

        return Inertia::render('User/Beranda', [
            'pengguna' => $pengguna,
            'targetKalori' => $targetKalori,
            'kaloriTerkonsumsi' => $kaloriTerkonsumsi,
            'streakHari' => $progress->forUser($user, $pengguna instanceof Pengguna ? $pengguna : null, CarbonImmutable::today())['streak']['saat_ini'],
            'keluarga' => $pengguna?->keluarga,
            'misi' => [
                'list' => $misiList,
                'completed_count' => $completedMissionsCount,
                'total_count' => count($misiList),
            ],
            'lencana' => [
                [
                    'id' => 'first_step',
                    'title' => 'Langkah Pertama',
                    'description' => 'Catat satu makanan',
                    'achieved' => count($catatanMakananHariIni) > 0,
                ],
                [
                    'id' => 'one_table',
                    'title' => 'Satu Meja',
                    'description' => 'Gabung grup',
                    'achieved' => ! is_null($pengguna?->keluarga_id),
                ],
            ],
        ]);
    }
}
