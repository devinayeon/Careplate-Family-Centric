<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AhliGizi;
use App\Models\Bmi;
use App\Models\KebutuhanKalori;
use App\Models\Konsultasi;
use App\Models\PelacakanMakanan;
use App\Models\PesanKonsultasi;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserConsultationController extends Controller
{
    /**
     * Tampilkan katalog ahli gizi dan daftar konsultasi saya.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('q', ''));
        $kategori = trim((string) $request->input('kategori', ''));
        $tab = $request->input('tab', 'semua');

        // Query Ahli Gizi
        $ahliGiziQuery = AhliGizi::with('user:id,name,email')
            ->where('is_aktif', true);

        if ($search !== '') {
            $ahliGiziQuery->where(function ($q) use ($search) {
                $q->where('spesialisasi', 'like', "%{$search}%")
                    ->orWhere('bio', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($kategori !== '' && $kategori !== 'Semua') {
            $ahliGiziQuery->where('spesialisasi', $kategori);
        }

        $ahliGiziList = $ahliGiziQuery->orderBy('id', 'asc')
            ->paginate(8)
            ->withQueryString();

        // Daftar kategori (spesialisasi unik)
        $kategoriList = AhliGizi::where('is_aktif', true)
            ->whereNotNull('spesialisasi')
            ->pluck('spesialisasi')
            ->unique()
            ->values()
            ->all();

        // Query Konsultasi Saya
        $konsultasiSaya = Konsultasi::with([
            'ahliGizi.user:id,name,email',
            'pesan' => function ($q) {
                $q->latest()->limit(1);
            },
        ])
            ->where('user_id', $request->user()->id)
            ->orderByRaw("FIELD(status, 'berlangsung', 'menunggu', 'selesai', 'ditolak')")
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($k) {
                $pesanTerakhir = $k->pesan->first();
                return [
                    'id' => $k->id,
                    'nomor_konsultasi' => $k->nomor_konsultasi,
                    'target_tipe' => $k->target_tipe,
                    'nama_pasien' => $k->nama_pasien,
                    'usia_pasien' => $k->usia_pasien,
                    'topik' => $k->topik,
                    'status' => $k->status,
                    'alasan_penolakan' => $k->alasan_penolakan,
                    'disetujui_pada' => $k->disetujui_pada?->format('d M Y, H:i'),
                    'selesai_pada' => $k->selesai_pada?->format('d M Y, H:i'),
                    'updated_at' => $k->updated_at?->diffForHumans(),
                    'created_at' => $k->created_at?->format('d M Y, H:i'),
                    'ahli_gizi' => [
                        'id' => $k->ahliGizi->id,
                        'nama' => $k->ahliGizi->user->name ?? 'Ahli Gizi',
                        'spesialisasi' => $k->ahliGizi->spesialisasi,
                        'nomor_str' => $k->ahliGizi->nomor_str,
                    ],
                    'pesan_terakhir' => $pesanTerakhir ? [
                        'pesan' => $pesanTerakhir->pesan,
                        'waktu' => $pesanTerakhir->created_at->format('H.i'),
                        'is_meeting' => $pesanTerakhir->tipe_pesan === 'meeting',
                    ] : null,
                ];
            });

        return Inertia::render('User/Konsultasi/Index', [
            'active_tab' => $tab,
            'filters' => [
                'q' => $search,
                'kategori' => $kategori,
            ],
            'kategori_list' => $kategoriList,
            'ahli_gizi_list' => $ahliGiziList,
            'konsultasi_saya' => $konsultasiSaya,
        ]);
    }

    /**
     * Tampilkan formulir pengajuan konsultasi untuk ahli gizi tertentu.
     */
    public function create(Request $request, AhliGizi $ahliGizi): Response
    {
        $ahliGizi->load('user:id,name,email');

        $user = $request->user()->load('pengguna');
        $pengguna = $user->pengguna;

        // Hitung umur user saat ini jika tanggal lahir ada
        $userAge = null;
        if ($pengguna && $pengguna->tanggal_lahir) {
            $userAge = Carbon::parse($pengguna->tanggal_lahir)->age;
        }

        // Ambil snapshot data kesehatan terkini (BMI, asupan kalori, log makanan 7 hari)
        $latestBmi = Bmi::where('pengguna_id', $pengguna?->id)->latest()->first();
        $latestKebutuhan = KebutuhanKalori::where('pengguna_id', $pengguna?->id)->latest()->first();

        // Rata-rata 7 hari
        $sevenDaysAgo = now()->subDays(7)->toDateString();
        $logs7Days = PelacakanMakanan::where('pengguna_id', $pengguna?->id)
            ->where('tanggal', '>=', $sevenDaysAgo)
            ->get();

        $totalKalori7 = $logs7Days->sum('kalori_tercatat');
        $avgKalori = $logs7Days->count() > 0 ? round($totalKalori7 / 7) : ($latestKebutuhan?->total_kalori_harian ?? 1800);

        $healthSnapshot = [
            'bmi_usia' => $latestBmi ? "{$latestBmi->kategori_bmi} (BMI {$latestBmi->skor_bmi})" : 'Normal (BMI 22.0)',
            'energi_rata_rata' => number_format((float) $avgKalori, 0, ',', '.') . ' kkal',
            'camilan_manis' => '1 - 2 kali per hari',
            'sayur_tercatat' => '4 dari 7 hari tercatat',
        ];

        return Inertia::render('User/Konsultasi/Create', [
            'ahli_gizi' => [
                'id' => $ahliGizi->id,
                'nama' => $ahliGizi->user->name ?? 'Ahli Gizi',
                'spesialisasi' => $ahliGizi->spesialisasi,
                'nomor_str' => $ahliGizi->nomor_str,
                'jam_layanan' => $ahliGizi->jam_layanan,
                'bio' => $ahliGizi->bio,
                'tarif_konsultasi' => (float) $ahliGizi->tarif_konsultasi,
            ],
            'user_profile' => [
                'nama' => $user->name,
                'usia' => $userAge ?? 28,
                'gender' => $pengguna?->jenis_kelamin ?? 'Laki-laki',
                'health_snapshot' => $healthSnapshot,
            ],
        ]);
    }

    /**
     * Simpan pengajuan konsultasi baru.
     */
    public function store(Request $request, AhliGizi $ahliGizi): RedirectResponse
    {
        $validated = $request->validate([
            'target_tipe' => 'required|in:diri_sendiri,orang_lain',
            'nama_pasien' => 'required|string|max:255',
            'usia_pasien' => 'required|integer|min:0|max:120',
            'keluhan' => 'required|string|min:10|max:3000',
            'sertakan_data_kesehatan' => 'nullable|boolean',
            'data_kesehatan' => 'nullable|array',
        ]);

        $konsultasi = Konsultasi::create([
            'nomor_konsultasi' => Konsultasi::generateNomor(),
            'user_id' => $request->user()->id,
            'ahli_gizi_id' => $ahliGizi->id,
            'target_tipe' => $validated['target_tipe'],
            'nama_pasien' => $validated['nama_pasien'],
            'usia_pasien' => $validated['usia_pasien'],
            'topik' => $ahliGizi->spesialisasi ?? 'Konsultasi Gizi',
            'keluhan' => $validated['keluhan'],
            'data_kesehatan' => !empty($validated['sertakan_data_kesehatan']) ? ($validated['data_kesehatan'] ?? null) : null,
            'status' => 'menunggu',
        ]);

        return redirect()->route('user.consultation.show', $konsultasi->id)
            ->with('success', 'Permohonan konsultasi berhasil diajukan! Menunggu persetujuan ahli gizi.');
    }

    /**
     * Tampilkan ruang konsultasi chat untuk klien.
     */
    public function show(Request $request, Konsultasi $konsultasi): Response|RedirectResponse
    {
        // Pastikan hanya pemilik pengajuan yang dapat mengakses
        if ($konsultasi->user_id !== $request->user()->id) {
            abort(403, 'Anda tidak memiliki akses ke konsultasi ini.');
        }

        $konsultasi->load([
            'ahliGizi.user:id,name,email',
            'pesan.sender:id,name',
        ]);

        // Tandai pesan dari ahli gizi sebagai dibaca
        PesanKonsultasi::where('konsultasi_id', $konsultasi->id)
            ->where('sender_id', '!=', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return Inertia::render('User/Konsultasi/Chat', [
            'konsultasi' => [
                'id' => $konsultasi->id,
                'nomor_konsultasi' => $konsultasi->nomor_konsultasi,
                'target_tipe' => $konsultasi->target_tipe,
                'nama_pasien' => $konsultasi->nama_pasien,
                'usia_pasien' => $konsultasi->usia_pasien,
                'topik' => $konsultasi->topik,
                'keluhan' => $konsultasi->keluhan,
                'data_kesehatan' => $konsultasi->data_kesehatan,
                'status' => $konsultasi->status,
                'alasan_penolakan' => $konsultasi->alasan_penolakan,
                'disetujui_pada' => $konsultasi->disetujui_pada?->format('d M Y, H:i'),
                'selesai_pada' => $konsultasi->selesai_pada?->format('d M Y, H:i'),
                'created_at' => $konsultasi->created_at?->format('d M Y, H:i'),
                'ahli_gizi' => [
                    'id' => $konsultasi->ahliGizi->id,
                    'user_id' => $konsultasi->ahliGizi->user_id,
                    'nama' => $konsultasi->ahliGizi->user->name ?? 'Ahli Gizi',
                    'spesialisasi' => $konsultasi->ahliGizi->spesialisasi,
                    'nomor_str' => $konsultasi->ahliGizi->nomor_str,
                    'jam_layanan' => $konsultasi->ahliGizi->jam_layanan,
                ],
                'messages' => $konsultasi->pesan->map(function ($p) use ($request) {
                    return [
                        'id' => $p->id,
                        'sender_id' => $p->sender_id,
                        'is_me' => $p->sender_id === $request->user()->id,
                        'sender_name' => $p->sender->name ?? 'Pengirim',
                        'tipe_pesan' => $p->tipe_pesan,
                        'pesan' => $p->pesan,
                        'metadata' => $p->metadata,
                        'time' => $p->created_at->format('H.i'),
                        'date' => $p->created_at->format('d M Y'),
                    ];
                }),
            ],
        ]);
    }

    /**
     * Kirim pesan baru dalam sesi konsultasi.
     */
    public function sendMessage(Request $request, Konsultasi $konsultasi): JsonResponse|RedirectResponse
    {
        if ($konsultasi->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($konsultasi->status !== 'berlangsung') {
            return response()->json([
                'success' => false,
                'message' => 'Sesi konsultasi tidak dalam status berlangsung (aktif).',
            ], 422);
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
                    'sender_id' => $pesan->sender_id,
                    'is_me' => true,
                    'sender_name' => $request->user()->name,
                    'tipe_pesan' => $pesan->tipe_pesan,
                    'pesan' => $pesan->pesan,
                    'metadata' => $pesan->metadata,
                    'time' => $pesan->created_at->format('H.i'),
                    'date' => $pesan->created_at->format('d M Y'),
                ],
            ]);
        }

        return redirect()->back();
    }

    /**
     * Real-time polling & sync endpoint untuk pesan baru dan update status.
     */
    public function streamMessages(Request $request, Konsultasi $konsultasi): JsonResponse
    {
        if ($konsultasi->user_id !== $request->user()->id) {
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

        // Tandai pesan dari dokter sebagai terbaca
        PesanKonsultasi::where('konsultasi_id', $konsultasi->id)
            ->where('sender_id', '!=', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'status' => $konsultasi->fresh()->status,
            'alasan_penolakan' => $konsultasi->alasan_penolakan,
            'messages' => $newMessages->map(function ($p) use ($request) {
                return [
                    'id' => $p->id,
                    'sender_id' => $p->sender_id,
                    'is_me' => $p->sender_id === $request->user()->id,
                    'sender_name' => $p->sender->name ?? 'Pengirim',
                    'tipe_pesan' => $p->tipe_pesan,
                    'pesan' => $p->pesan,
                    'metadata' => $p->metadata,
                    'time' => $p->created_at->format('H.i'),
                    'date' => $p->created_at->format('d M Y'),
                ];
            }),
        ]);
    }
}
