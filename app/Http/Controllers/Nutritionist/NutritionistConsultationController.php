<?php

namespace App\Http\Controllers\Nutritionist;

use App\Http\Controllers\Controller;
use App\Models\AhliGizi;
use App\Models\Konsultasi;
use App\Models\PesanKonsultasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NutritionistConsultationController extends Controller
{
    /**
     * Tampilkan workspace konsultasi interaktif ahli gizi.
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

        $currentTab = $request->input('tab', 'berlangsung');
        if (!in_array($currentTab, ['menunggu', 'berlangsung', 'selesai', 'ditolak'])) {
            $currentTab = 'berlangsung';
        }

        // Tab counts
        $counts = [
            'menunggu' => Konsultasi::where('ahli_gizi_id', $ahliGizi->id)->where('status', 'menunggu')->count(),
            'berlangsung' => Konsultasi::where('ahli_gizi_id', $ahliGizi->id)->where('status', 'berlangsung')->count(),
            'selesai' => Konsultasi::where('ahli_gizi_id', $ahliGizi->id)->where('status', 'selesai')->count(),
            'ditolak' => Konsultasi::where('ahli_gizi_id', $ahliGizi->id)->where('status', 'ditolak')->count(),
        ];

        // Jika tab saat ini kosong dan ada tab menunggu/berlangsung, sesuaikan secara pintar
        if ($counts[$currentTab] === 0 && $counts['berlangsung'] > 0 && $currentTab === 'menunggu') {
            $currentTab = 'berlangsung';
        } elseif ($counts[$currentTab] === 0 && $counts['menunggu'] > 0 && $currentTab === 'berlangsung') {
            $currentTab = 'menunggu';
        }

        // Daftar klien sesuai tab terpilih
        $klienListRaw = Konsultasi::with([
            'user:id,name,email',
            'pesan' => function ($q) {
                $q->latest()->limit(1);
            },
        ])
            ->where('ahli_gizi_id', $ahliGizi->id)
            ->where('status', $currentTab)
            ->orderByDesc('updated_at')
            ->get();

        $klienList = $klienListRaw->map(function ($k) {
            $nama = $k->user->name ?? $k->nama_pasien;
            $words = explode(' ', trim($nama));
            $inisial = '';
            foreach ($words as $w) {
                if ($w !== '') {
                    $inisial .= strtoupper($w[0]);
                }
                if (strlen($inisial) >= 2) break;
            }
            if ($inisial === '') $inisial = 'KL';

            $pesanTerakhir = $k->pesan->first();

            return [
                'id' => $k->id,
                'inisial' => $inisial,
                'nama' => $nama,
                'subjudul' => $k->target_tipe === 'orang_lain'
                    ? "{$k->topik} • {$k->nama_pasien}, {$k->usia_pasien} th"
                    : "{$k->topik} • {$k->usia_pasien} th",
                'waktu' => $pesanTerakhir ? $pesanTerakhir->created_at->format('H.i') : $k->created_at->format('d M'),
                'unread' => PesanKonsultasi::where('konsultasi_id', $k->id)
                    ->where('sender_id', '!=', auth()->id())
                    ->where('is_read', false)
                    ->exists(),
                'status' => $k->status,
                'created_at' => $k->created_at->format('d M, H.i'),
            ];
        });

        // Tentukan active client
        $selectedId = $request->input('id');
        $activeKonsultasi = null;

        if ($selectedId) {
            $activeKonsultasi = Konsultasi::with(['user', 'pesan.sender'])
                ->where('ahli_gizi_id', $ahliGizi->id)
                ->find($selectedId);
        }

        if (!$activeKonsultasi && $klienListRaw->isNotEmpty()) {
            $activeKonsultasi = Konsultasi::with(['user', 'pesan.sender'])
                ->where('ahli_gizi_id', $ahliGizi->id)
                ->find($klienListRaw->first()->id);
        }

        $activeClientData = null;
        if ($activeKonsultasi) {
            // Tandai pesan dari klien sebagai terbaca
            PesanKonsultasi::where('konsultasi_id', $activeKonsultasi->id)
                ->where('sender_id', '!=', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            $nama = $activeKonsultasi->user->name ?? $activeKonsultasi->nama_pasien;
            $words = explode(' ', trim($nama));
            $inisial = '';
            foreach ($words as $w) {
                if ($w !== '') {
                    $inisial .= strtoupper($w[0]);
                }
                if (strlen($inisial) >= 2) break;
            }

            $health = $activeKonsultasi->data_kesehatan ?? [
                'bmi_usia' => 'Normal',
                'energi_rata_rata' => '1.420 kkal',
                'camilan_manis' => '2 kali per hari',
                'sayur_tercatat' => '3 dari 7 hari',
            ];

            $activeClientData = [
                'id' => $activeKonsultasi->id,
                'nomor_konsultasi' => $activeKonsultasi->nomor_konsultasi,
                'inisial' => $inisial ?: 'KL',
                'nama' => $nama,
                'subjudul' => "{$activeKonsultasi->nama_pasien}, {$activeKonsultasi->usia_pasien} th • {$activeKonsultasi->topik}",
                'status' => $activeKonsultasi->status,
                'alasan_penolakan' => $activeKonsultasi->alasan_penolakan,
                'klien_info' => [
                    'untuk' => "{$activeKonsultasi->nama_pasien}, {$activeKonsultasi->usia_pasien} tahun",
                    'topik' => $activeKonsultasi->topik,
                    'diajukan' => $activeKonsultasi->created_at->format('d M, H.i'),
                    'keluhan' => $activeKonsultasi->keluhan,
                    'data_dibagikan' => $health,
                ],
                'messages' => $activeKonsultasi->pesan->map(function ($p) use ($user) {
                    return [
                        'id' => $p->id,
                        'sender' => $p->sender_id === $user->id ? 'nutritionist' : 'client',
                        'is_me' => $p->sender_id === $user->id,
                        'sender_name' => $p->sender->name ?? 'Pengguna',
                        'tipe_pesan' => $p->tipe_pesan,
                        'content' => $p->pesan,
                        'metadata' => $p->metadata,
                        'time' => $p->created_at->format('H.i'),
                        'date' => $p->created_at->format('d M Y'),
                    ];
                }),
            ];
        }

        return Inertia::render('Nutritionist/Konsultasi', [
            'active_tab' => $currentTab,
            'tabs' => [
                'menunggu' => $counts['menunggu'],
                'berlangsung' => $counts['berlangsung'],
                'selesai' => $counts['selesai'],
                'ditolak' => $counts['ditolak'],
            ],
            'klien_list' => $klienList,
            'active_client' => $activeClientData,
        ]);
    }

    /**
     * Setujui permohonan konsultasi dari pengguna.
     */
    public function approve(Request $request, Konsultasi $konsultasi): RedirectResponse
    {
        $ahliGizi = AhliGizi::where('user_id', $request->user()->id)->firstOrFail();
        if ($konsultasi->ahli_gizi_id !== $ahliGizi->id) {
            abort(403);
        }

        $konsultasi->update([
            'status' => 'berlangsung',
            'disetujui_pada' => now(),
        ]);

        // Berikan pesan pembuka otomatis dari sistem / ahli gizi
        PesanKonsultasi::create([
            'konsultasi_id' => $konsultasi->id,
            'sender_id' => $request->user()->id,
            'tipe_pesan' => 'teks',
            'pesan' => "Halo {$konsultasi->nama_pasien}, permohonan konsultasi Anda telah disetujui. Silakan sampaikan lebih detail atau kita dapat menjadwalkan sesi meeting online.",
            'is_read' => false,
        ]);

        return redirect()->route('nutritionist.consultations', [
            'tab' => 'berlangsung',
            'id' => $konsultasi->id,
        ])->with('success', 'Permohonan konsultasi berhasil disetujui.');
    }

    /**
     * Tolak permohonan konsultasi dengan mencantumkan alasan.
     */
    public function reject(Request $request, Konsultasi $konsultasi): RedirectResponse
    {
        $ahliGizi = AhliGizi::where('user_id', $request->user()->id)->firstOrFail();
        if ($konsultasi->ahli_gizi_id !== $ahliGizi->id) {
            abort(403);
        }

        $validated = $request->validate([
            'alasan_penolakan' => 'required|string|min:5|max:1000',
        ]);

        $konsultasi->update([
            'status' => 'ditolak',
            'alasan_penolakan' => $validated['alasan_penolakan'],
            'ditolak_pada' => now(),
        ]);

        return redirect()->route('nutritionist.consultations', [
            'tab' => 'ditolak',
            'id' => $konsultasi->id,
        ])->with('success', 'Permohonan konsultasi telah ditolak.');
    }

    /**
     * Selesaikan sesi konsultasi (tutup ruang percakapan).
     */
    public function complete(Request $request, Konsultasi $konsultasi): RedirectResponse
    {
        $ahliGizi = AhliGizi::where('user_id', $request->user()->id)->firstOrFail();
        if ($konsultasi->ahli_gizi_id !== $ahliGizi->id) {
            abort(403);
        }

        $konsultasi->update([
            'status' => 'selesai',
            'selesai_pada' => now(),
        ]);

        PesanKonsultasi::create([
            'konsultasi_id' => $konsultasi->id,
            'sender_id' => $request->user()->id,
            'tipe_pesan' => 'sistem',
            'pesan' => 'Sesi konsultasi ini telah resmi diselesaikan oleh Ahli Gizi. Ruang percakapan ditutup.',
            'is_read' => true,
        ]);

        return redirect()->route('nutritionist.consultations', [
            'tab' => 'selesai',
            'id' => $konsultasi->id,
        ])->with('success', 'Sesi konsultasi berhasil ditandai selesai.');
    }

    /**
     * Buat dan kirim kartu tautan meeting (Google Meet / Zoom).
     */
    public function createMeeting(Request $request, Konsultasi $konsultasi): RedirectResponse|JsonResponse
    {
        $ahliGizi = AhliGizi::where('user_id', $request->user()->id)->firstOrFail();
        if ($konsultasi->ahli_gizi_id !== $ahliGizi->id) {
            abort(403);
        }

        if ($konsultasi->status !== 'berlangsung') {
            return response()->json(['success' => false, 'message' => 'Hanya dapat menjadwalkan meeting pada konsultasi aktif.'], 422);
        }

        $validated = $request->validate([
            'tanggal' => 'required|string|max:100',
            'mulai' => 'required|string|max:50',
            'durasi' => 'required|string|max:50',
            'link' => 'required|string|max:500',
        ]);

        $pesan = PesanKonsultasi::create([
            'konsultasi_id' => $konsultasi->id,
            'sender_id' => $request->user()->id,
            'tipe_pesan' => 'meeting',
            'pesan' => 'Jadwal tatap muka konsultasi gizi online telah dibuat.',
            'metadata' => [
                'tanggal' => $validated['tanggal'],
                'mulai' => $validated['mulai'],
                'durasi' => $validated['durasi'],
                'link' => $validated['link'],
            ],
            'is_read' => false,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Meeting berhasil dijadwalkan.',
                'data' => $pesan,
            ]);
        }

        return redirect()->back()->with('success', 'Tautan meeting berhasil dikirim ke klien.');
    }

    /**
     * Kirim pesan balasan ahli gizi.
     */
    public function sendMessage(Request $request, Konsultasi $konsultasi): RedirectResponse|JsonResponse
    {
        $ahliGizi = AhliGizi::where('user_id', $request->user()->id)->firstOrFail();
        if ($konsultasi->ahli_gizi_id !== $ahliGizi->id) {
            abort(403);
        }

        if ($konsultasi->status !== 'berlangsung') {
            return response()->json(['success' => false, 'message' => 'Sesi tidak aktif.'], 422);
        }

        $validated = $request->validate([
            'pesan' => 'required|string|min:1|max:3000',
        ]);

        $pesan = PesanKonsultasi::create([
            'konsultasi_id' => $konsultasi->id,
            'sender_id' => $request->user()->id,
            'tipe_pesan' => 'teks',
            'pesan' => $validated['pesan'],
            'is_read' => false,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message_data' => [
                    'id' => $pesan->id,
                    'sender' => 'nutritionist',
                    'is_me' => true,
                    'sender_name' => $request->user()->name,
                    'tipe_pesan' => 'teks',
                    'content' => $pesan->pesan,
                    'time' => $pesan->created_at->format('H.i'),
                    'date' => $pesan->created_at->format('d M Y'),
                ],
            ]);
        }

        return redirect()->back();
    }

    /**
     * Endpoint sinkronisasi pesan baru secara real-time.
     */
    public function streamMessages(Request $request, Konsultasi $konsultasi): JsonResponse
    {
        $ahliGizi = AhliGizi::where('user_id', $request->user()->id)->firstOrFail();
        if ($konsultasi->ahli_gizi_id !== $ahliGizi->id) {
            abort(403);
        }

        $afterId = (int) $request->input('after_id', 0);

        $newMessages = PesanKonsultasi::with('sender:id,name')
            ->where('konsultasi_id', $konsultasi->id)
            ->when($afterId > 0, function ($q) use ($afterId) {
                $q->where('id', '>', $afterId);
            })
            ->orderBy('id', 'asc')
            ->get();

        // Tandai pesan klien sebagai terbaca
        PesanKonsultasi::where('konsultasi_id', $konsultasi->id)
            ->where('sender_id', '!=', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'status' => $konsultasi->fresh()->status,
            'messages' => $newMessages->map(function ($p) use ($request) {
                return [
                    'id' => $p->id,
                    'sender' => $p->sender_id === $request->user()->id ? 'nutritionist' : 'client',
                    'is_me' => $p->sender_id === $request->user()->id,
                    'sender_name' => $p->sender->name ?? 'Pengirim',
                    'tipe_pesan' => $p->tipe_pesan,
                    'content' => $p->pesan,
                    'metadata' => $p->metadata,
                    'time' => $p->created_at->format('H.i'),
                    'date' => $p->created_at->format('d M Y'),
                ];
            }),
        ]);
    }
}
