<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Artikel;
use App\Models\Keluarga;
use App\Models\Makanan;
use App\Models\Pengguna;
use App\Models\User;
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

        // 7. Status Sinkronisasi Basis Data & AI Chat Gizi Real
        $lastSyncFood = Makanan::where('status', 'terverifikasi')->latest('updated_at')->first();
        $terakhirSinkron = $lastSyncFood && $lastSyncFood->updated_at
            ? Carbon::parse($lastSyncFood->updated_at)->diffForHumans()
            : 'Terkini';

        // Deteksi anomali integritas data (contoh: makanan terverifikasi tapi energi null/<=0)
        $anomaliItem = Makanan::where('status', 'terverifikasi')
            ->where(function ($q) {
                $q->whereNull('energi')->orWhere('energi', '<=', 0);
            })
            ->first();

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
                    'detail' => "{$totalMakananTerverifikasi} terverifikasi" . ($totalMakananMenunggu > 0 ? " • {$totalMakananMenunggu} menunggu" : ''),
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
                'status' => 'Terkini',
                'terakhir_sinkron' => $terakhirSinkron,
                'error_item' => $anomaliItem ? $anomaliItem->nama : null,
            ],
        ]);
    }
}
