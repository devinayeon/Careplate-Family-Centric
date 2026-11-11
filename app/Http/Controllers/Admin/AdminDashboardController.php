<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Keluarga;
use App\Models\Makanan;
use App\Models\User;
use App\Services\Ai\Foods\AiFoodStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        // Pengajuan makanan: the three most recent submissions (anything a user sent in), and the real waiting queue.
        // A submission is a row that is waiting or went through a review; foods the admin typed in directly are not.
        $pengajuanTerbaru = Makanan::query()->where(fn ($q) => $q->where('status', 'menunggu')->orWhereNotNull('diverifikasi_oleh'))
            ->latest('id')->limit(3)->get()
            ->map(fn (Makanan $m) => [
                'id' => $m->id,
                'nama' => $m->nama,
                'pengaju' => $m->nama_pengaju ?: 'Pengguna',
                'tanggal' => $m->created_at?->translatedFormat('j M') ?? '',
                'status' => match ($m->status) {
                    'terverifikasi' => 'Terverifikasi',
                    'ditolak' => 'Ditolak',
                    default => 'Menunggu',
                },
            ])->all();
        $menunggu = Makanan::query()->where('status', 'menunggu');
        $tertua = (clone $menunggu)->min('created_at');
        $ai = AiFoodStatus::summary();

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
                'jumlah' => (clone $menunggu)->count(),
                'terlama_hari' => $tertua === null ? 0 : (int) Carbon::parse($tertua)->diffInDays(now(), true),
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
