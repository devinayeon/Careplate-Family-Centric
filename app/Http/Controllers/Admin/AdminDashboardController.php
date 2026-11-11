<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Artikel;
use App\Models\Keluarga;
use App\Models\Makanan;
use App\Models\Pengguna;
use App\Models\User;
use App\Services\Ai\Foods\AiFoodStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    /**
     * Tampilkan halaman Dashboard Admin dengan data real dari database.
     */
    public function index(Request $request): Response
    {
        // 1. Statistik Pengguna Real
        $totalCustomer = User::role('user')->count();
        $totalAhliGizi = User::role(['ahli_gizi', 'nutritionist'])->count();
        $totalAdmin = User::role('admin')->count();
        $totalUsers = User::count();

        // 2. Statistik Artikel Real
        $totalArtikel = Artikel::count();
        $totalArtikelTerbit = Artikel::where('status', 'terbit')->count();
        $totalArtikelDraf = Artikel::where('status', 'draf')->count();

        // 3. Statistik Database Makanan Real
        $totalMakanan = Makanan::count();
        $totalMakananTerverifikasi = Makanan::where('status', 'terverifikasi')->count();
        $totalMakananMenunggu = Makanan::where('status', 'menunggu')->count();

        // 4. Statistik Grup Keluarga Real
        $totalKeluarga = Keluarga::count();
        $totalAnggota = Pengguna::whereNotNull('keluarga_id')->count();
        $avgAnggota = $totalKeluarga > 0
            ? number_format($totalAnggota / $totalKeluarga, 1, ',', '.')
            : '0';

        // 5. Antrean Verifikasi Makanan Real
        $antreanQuery = Makanan::where('status', 'menunggu');
        $jumlahPending = $antreanQuery->count();
        $oldestPending = (clone $antreanQuery)->oldest('created_at')->first();
        $terlamaHari = $oldestPending && $oldestPending->created_at
            ? (int) max(0, Carbon::now()->diffInDays($oldestPending->created_at))
            : 0;

        // 6. Pengajuan Makanan Terbaru Real (5 item teratas)
        $pengajuanTerbaru = Makanan::with('pengaju')
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(function (Makanan $item) {
                return [
                    'id' => $item->id,
                    'nama' => $item->nama,
                    'pengaju' => $item->pengaju?->name ?? $item->nama_pengaju ?: 'Pengguna CarePlate',
                    'tanggal' => Carbon::parse($item->created_at)->translatedFormat('j M'),
                    'status' => ucfirst($item->status),
                ];
            });

        // 7. Sinkronisasi Chat Gizi: real state of the verified foods the AI chat can use (see AiFoodStatus)
        $ai = AiFoodStatus::summary();

        return Inertia::render('Admin/Dashboard', [
            'metrics' => [
                'total_pengguna' => [
                    'total' => number_format($totalUsers, 0, ',', '.'),
                    'detail' => "Customer {$totalCustomer} • Ahli Gizi {$totalAhliGizi} • Admin {$totalAdmin}",
                ],
                'total_artikel' => [
                    'total' => number_format($totalArtikel, 0, ',', '.'),
                    'detail' => "{$totalArtikelTerbit} terbit • {$totalArtikelDraf} draf",
                ],
                'total_makanan' => [
                    'total' => number_format($totalMakanan, 0, ',', '.'),
                    'detail' => "{$totalMakananTerverifikasi} terverifikasi".($totalMakananMenunggu > 0 ? " • {$totalMakananMenunggu} menunggu" : ''),
                ],
                'total_keluarga' => [
                    'total' => number_format($totalKeluarga, 0, ',', '.'),
                    'detail' => $totalKeluarga > 0 ? "Rata-rata {$avgAnggota} anggota" : 'Belum ada grup keluarga',
                ],
            ],
            'antrean_verifikasi' => [
                'jumlah' => $jumlahPending,
                'terlama_hari' => $terlamaHari,
            ],
            'pengajuan_terbaru' => $pengajuanTerbaru,
            'sinkronisasi_ai' => [
                'ready' => $ai['ready'],
                'needs_weight' => $ai['needs_weight'],
                'failed' => $ai['failed'],
                'failed_names' => $ai['failed_names'],
                'terakhir_sinkron' => $ai['last_sync']?->locale('id')->diffForHumans(),
            ],
        ]);
    }
}
