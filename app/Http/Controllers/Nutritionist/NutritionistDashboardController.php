<?php

namespace App\Http\Controllers\Nutritionist;

use App\Http\Controllers\Controller;
use App\Models\AhliGizi;
use App\Models\Konsultasi;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NutritionistDashboardController extends Controller
{
    /**
     * Tampilkan halaman Dashboard Ikhtisar Ahli Gizi dengan data riil database.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $ahliGizi = AhliGizi::firstOrCreate(
            ['user_id' => $user->id],
            [
                'spesialisasi' => 'Gizi Keluarga',
                'nomor_str' => '1234 5678 9012 3456',
                'jam_layanan' => 'Senin - Sabtu, 08.00 - 16.00 WIB',
                'bio' => 'Tenaga gizi profesional CarePlate.',
                'is_aktif' => true,
            ]
        );

        $klienAktifCount = Konsultasi::where('ahli_gizi_id', $ahliGizi->id)
            ->where('status', 'berlangsung')
            ->count();

        $konsultasiHariIniCount = Konsultasi::where('ahli_gizi_id', $ahliGizi->id)
            ->whereDate('updated_at', now()->toDateString())
            ->count();

        $menungguResponCount = Konsultasi::where('ahli_gizi_id', $ahliGizi->id)
            ->where('status', 'menunggu')
            ->count();

        // Antrean konsultasi butuh verifikasi (menunggu) dan aktif
        $antreanList = Konsultasi::with([
            'user:id,name,email',
            'pesan' => function ($q) {
                $q->latest()->limit(1);
            },
        ])
            ->where('ahli_gizi_id', $ahliGizi->id)
            ->whereIn('status', ['menunggu', 'berlangsung'])
            ->orderByRaw("FIELD(status, 'menunggu', 'berlangsung')")
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(function ($k) {
                $pesanTerakhir = $k->pesan->first();
                return [
                    'id' => $k->id,
                    'nama' => $k->user->name ?? $k->nama_pasien,
                    'subjudul' => "{$k->topik} • {$k->nama_pasien}, {$k->usia_pasien} th",
                    'pesan_terakhir' => $pesanTerakhir ? $pesanTerakhir->pesan : $k->keluhan,
                    'waktu' => $pesanTerakhir ? $pesanTerakhir->created_at->format('H.i') : $k->created_at->format('d M'),
                    'status' => $k->status,
                    'unread' => $k->status === 'menunggu',
                ];
            });

        // Jadwal konsultasi (dari pesan tipe meeting pada konsultasi aktif)
        $jadwalHariIni = Konsultasi::with(['user', 'pesan' => function ($q) {
            $q->where('tipe_pesan', 'meeting')->latest();
        }])
            ->where('ahli_gizi_id', $ahliGizi->id)
            ->where('status', 'berlangsung')
            ->whereHas('pesan', function ($q) {
                $q->where('tipe_pesan', 'meeting');
            })
            ->take(4)
            ->get()
            ->map(function ($k) {
                $meetingMsg = $k->pesan->first();
                $meta = $meetingMsg?->metadata ?? [];
                return [
                    'id' => $k->id,
                    'klien_nama' => $k->user->name ?? $k->nama_pasien,
                    'topik' => $k->topik,
                    'jam' => ($meta['mulai'] ?? '10:00') . ' (' . ($meta['durasi'] ?? '30 mnt') . ')',
                    'tanggal' => $meta['tanggal'] ?? 'Hari ini',
                    'link' => $meta['link'] ?? '#',
                    'status' => 'Berlangsung',
                ];
            });

        return Inertia::render('Nutritionist/Dashboard', [
            'stats' => [
                'klien_aktif' => $klienAktifCount,
                'konsultasi_hari_ini' => $konsultasiHariIniCount,
                'menunggu_respon' => $menungguResponCount,
                'rating_kepuasan' => '4.9/5.0',
            ],
            'jadwal_hari_ini' => $jadwalHariIni,
            'antrean_konsultasi' => $antreanList,
        ]);
    }
}
