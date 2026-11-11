<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\PelacakanMakanan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserDashboardController extends Controller
{
    /**
     * Tampilkan halaman Beranda Pengguna (Customer).
     */
    public function index(Request $request): Response
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

        // Misi harian
        $misiList = [
            [
                'id' => 1,
                'title' => 'Catat sarapan',
                'action_label' => 'Catat',
                'route' => 'user.meals',
                'completed' => $catatanMakananHariIni->isNotEmpty(),
            ],
            [
                'id' => 2,
                'title' => 'Catat tiga waktu makan',
                'action_label' => 'Lihat catatan',
                'route' => 'user.meals',
                'completed' => $catatanMakananHariIni->count() >= 3,
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
            'streakHari' => 0,
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
                    'achieved' => $catatanMakananHariIni->isNotEmpty(),
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
