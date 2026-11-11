<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Keluarga;
use App\Models\Makanan;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    /**
     * Tampilkan halaman Dashboard Admin.
     */
    public function index(Request $request): Response
    {
        // Hitung statistik pengguna berdasarkan role
        $totalCustomer = User::role('user')->count();
        if ($totalCustomer === 0) {
            $totalCustomer = 2390; // Fallback data demo untuk preview sesuai figma
        }
        $totalAhliGizi = User::role(['ahli_gizi', 'nutritionist'])->count();
        if ($totalAhliGizi === 0) {
            $totalAhliGizi = 21;
        }
        $totalAdmin = User::role('admin')->count();
        if ($totalAdmin === 0) {
            $totalAdmin = 7;
        }
        $totalUserAll = $totalCustomer + $totalAhliGizi + $totalAdmin;

        $totalMakanan = Makanan::count();
        if ($totalMakanan < 50) {
            $totalMakananFormatted = '1.276';
        } else {
            $totalMakananFormatted = number_format($totalMakanan, 0, ',', '.');
        }

        $totalKeluarga = Keluarga::count();
        if ($totalKeluarga === 0) {
            $totalKeluargaFormatted = '532';
            $avgAnggota = '3,4';
        } else {
            $totalKeluargaFormatted = number_format($totalKeluarga, 0, ',', '.');
            $avgAnggota = '3,4';
        }

        // Pengajuan makanan (menunggu verifikasi)
        $pengajuanTerbaru = [
            [
                'id' => 1,
                'nama' => 'Oseng tempe kecap',
                'pengaju' => 'Rina Santoso',
                'tanggal' => '3 Nov',
                'status' => 'Menunggu',
            ],
            [
                'id' => 2,
                'nama' => 'Kerupuk udang',
                'pengaju' => 'Hendra P.',
                'tanggal' => '2 Nov',
                'status' => 'Menunggu',
            ],
            [
                'id' => 3,
                'nama' => 'Sayur bening bayam',
                'pengaju' => 'Maya W.',
                'tanggal' => '28 Okt',
                'status' => 'Terverifikasi',
            ],
        ];

        return Inertia::render('Admin/Dashboard', [
            'metrics' => [
                'total_pengguna' => [
                    'total' => number_format($totalUserAll, 0, ',', '.'),
                    'detail' => "Customer {$totalCustomer} • Ahli Gizi {$totalAhliGizi} • Admin {$totalAdmin}",
                ],
                'total_artikel' => [
                    'total' => '64',
                    'detail' => '58 terbit • 6 draf',
                ],
                'total_makanan' => [
                    'total' => $totalMakananFormatted,
                    'detail' => 'Terverifikasi',
                ],
                'total_keluarga' => [
                    'total' => $totalKeluargaFormatted,
                    'detail' => "Rata-rata {$avgAnggota} anggota",
                ],
            ],
            'antrean_verifikasi' => [
                'jumlah' => 12,
                'terlama_hari' => 2,
            ],
            'pengajuan_terbaru' => $pengajuanTerbaru,
            'sinkronisasi_ai' => [
                'status' => 'Terkini',
                'terakhir_sinkron' => '2 menit lalu',
                'error_item' => 'Sayur bening bayam',
            ],
        ]);
    }
}
