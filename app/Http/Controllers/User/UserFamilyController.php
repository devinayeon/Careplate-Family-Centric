<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Keluarga;
use App\Models\PelacakanMakanan;
use App\Models\Pengguna;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserFamilyController extends Controller
{
    /**
     * Tampilkan halaman utama Keluarga (NoFamily jika belum punya, Meja Keluarga jika sudah punya).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        /** @var Pengguna $pengguna */
        $pengguna = $user->pengguna()->firstOrCreate([]);

        // 1. Jika belum bergabung ke grup keluarga manapun
        if (! $pengguna->keluarga_id) {
            return Inertia::render('User/Keluarga/NoFamily');
        }

        // 2. Ambil data grup keluarga
        $keluarga = Keluarga::with(['kepalaKeluarga.user', 'anggota.user'])->find($pengguna->keluarga_id);

        if (! $keluarga) {
            $pengguna->update(['keluarga_id' => null]);

            return Inertia::render('User/Keluarga/NoFamily');
        }

        // 3. Aktivitas Mingguan (Senin - Minggu minggu berjalan, otomatis ter-reset per minggu)
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $endOfWeek = Carbon::now()->endOfWeek(Carbon::SUNDAY)->endOfDay();
        $today = Carbon::today();

        $periodeMinggu = $startOfWeek->translatedFormat('d M').' - '.$endOfWeek->translatedFormat('d M Y');

        $daysOfWeek = [
            ['label' => 'Sn', 'date' => $startOfWeek->copy()->addDays(0)],
            ['label' => 'Sl', 'date' => $startOfWeek->copy()->addDays(1)],
            ['label' => 'Rb', 'date' => $startOfWeek->copy()->addDays(2)],
            ['label' => 'Km', 'date' => $startOfWeek->copy()->addDays(3)],
            ['label' => 'Jm', 'date' => $startOfWeek->copy()->addDays(4)],
            ['label' => 'Sb', 'date' => $startOfWeek->copy()->addDays(5)],
            ['label' => 'Mg', 'date' => $startOfWeek->copy()->addDays(6)],
        ];

        $targetTercapaiCount = 0;

        // Ambil data anggota dan aktivitasnya
        $anggotaList = $keluarga->anggota->map(function (Pengguna $member) use ($daysOfWeek, $startOfWeek, $endOfWeek, $today, $keluarga, &$targetTercapaiCount) {
            $memberName = $member->user?->name ?? 'Anggota';
            $isHead = ($member->id === $keluarga->kepala_keluarga_id);

            // Log pencatatan makanan dalam rentang minggu ini
            $weeklyLogs = PelacakanMakanan::query()
                ->where('pengguna_id', $member->id)
                ->whereBetween('tanggal_konsumsi', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
                ->pluck('tanggal_konsumsi')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->unique()
                ->toArray();

            $weeklyDots = [];
            foreach ($daysOfWeek as $day) {
                $dateStr = $day['date']->toDateString();
                $isLogged = in_array($dateStr, $weeklyLogs, true);
                $isFuture = $day['date']->isAfter(Carbon::today());
                $weeklyDots[] = [
                    'label' => $day['label'],
                    'date' => $dateStr,
                    'logged' => $isLogged,
                    'is_today' => $day['date']->isToday(),
                    'is_future' => $isFuture,
                ];
            }

            $totalDaysLogged = count($weeklyLogs);
            $targetAchieved = ($totalDaysLogged >= 5);
            if ($targetAchieved) {
                $targetTercapaiCount++;
            }

            // Log Makanan & Kalori Hari Ini
            $todayLogs = PelacakanMakanan::with('makanan')
                ->where('pengguna_id', $member->id)
                ->whereDate('tanggal_konsumsi', $today)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function (PelacakanMakanan $item) {
                    return [
                        'id' => $item->id,
                        'nama_makanan' => $item->makanan?->nama_makanan ?? 'Makanan',
                        'kategori_makanan' => $item->makanan?->kategori ?? 'Umum',
                        'waktu_konsumsi' => $item->waktu_konsumsi,
                        'jumlah_porsi' => (float) $item->jumlah_porsi,
                        'total_kalori' => (float) $item->total_kalori,
                    ];
                });

            $totalCaloriesToday = (float) $todayLogs->sum('total_kalori');

            return [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $memberName,
                'email' => $member->user?->email ?? '',
                'initials' => $this->getInitials($memberName),
                'is_kepala' => $isHead,
                'role_label' => $isHead ? 'Kepala Keluarga' : 'Anggota',
                'usia' => $member->usia,
                'total_hari_minggu_ini' => $totalDaysLogged,
                'target_tercapai' => $targetAchieved,
                'weekly_dots' => $weeklyDots,
                'total_kalori_hari_ini' => $totalCaloriesToday,
                'target_kalori_harian' => (float) ($member->kalori ?? 2000),
                'catatan_hari_ini' => $todayLogs,
            ];
        });

        $isKepalaKeluarga = ($keluarga->kepala_keluarga_id === $pengguna->id);

        return Inertia::render('User/Keluarga/Index', [
            'keluarga' => [
                'id' => $keluarga->id,
                'nama_keluarga' => $keluarga->nama_keluarga,
                'kode_keluarga' => $keluarga->kode_keluarga,
                'kepala_keluarga_nama' => $keluarga->kepalaKeluarga?->user?->name ?? 'Kepala Keluarga',
                'total_anggota' => $keluarga->anggota->count(),
                'link_undangan' => route('user.family.join_link', ['kode' => $keluarga->kode_keluarga]),
            ],
            'isKepalaKeluarga' => $isKepalaKeluarga,
            'currentUserId' => $pengguna->id,
            'periodeMinggu' => $periodeMinggu,
            'targetTercapaiCount' => $targetTercapaiCount,
            'totalAnggota' => $keluarga->anggota->count(),
            'anggotaList' => $anggotaList,
        ]);
    }

    /**
     * Halaman form pembuatan grup keluarga.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $pengguna = $user->pengguna()->firstOrCreate([]);

        if ($pengguna->keluarga_id) {
            return redirect()->route('user.family')->with('warning', 'Kamu sudah tergabung dalam grup keluarga.');
        }

        return Inertia::render('User/Keluarga/Create');
    }

    /**
     * Cek ketersediaan nama keluarga secara asinkron.
     */
    public function checkName(Request $request): JsonResponse
    {
        $nama = trim((string) $request->input('nama_keluarga', ''));

        if (mb_strlen($nama) < 3) {
            return response()->json([
                'available' => false,
                'message' => 'Nama keluarga minimal 3 karakter.',
            ]);
        }

        $exists = Keluarga::where('nama_keluarga', $nama)->exists();

        return response()->json([
            'available' => ! $exists,
            'message' => $exists ? 'Nama keluarga sudah digunakan.' : 'Nama keluarga tersedia.',
        ]);
    }

    /**
     * Simpan grup keluarga baru dan tetapkan pengguna sebagai Kepala Keluarga.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        /** @var Pengguna $pengguna */
        $pengguna = $user->pengguna()->firstOrCreate([]);

        if ($pengguna->keluarga_id) {
            return redirect()->route('user.family')->with('error', 'Kamu sudah memiliki grup keluarga.');
        }

        $validated = $request->validate([
            'nama_keluarga' => ['required', 'string', 'min:3', 'max:40', 'unique:keluarga,nama_keluarga'],
        ], [
            'nama_keluarga.required' => 'Nama grup keluarga wajib diisi.',
            'nama_keluarga.min' => 'Nama keluarga minimal 3 karakter.',
            'nama_keluarga.max' => 'Nama keluarga maksimal 40 karakter.',
            'nama_keluarga.unique' => 'Nama keluarga ini sudah digunakan oleh grup lain.',
        ]);

        $kode = Keluarga::generateKodeKeluarga();

        $keluarga = Keluarga::create([
            'nama_keluarga' => $validated['nama_keluarga'],
            'kode_keluarga' => $kode,
            'kepala_keluarga_id' => $pengguna->id,
        ]);

        $pengguna->update([
            'keluarga_id' => $keluarga->id,
        ]);

        return redirect()->route('user.family')->with('success', "Grup {$keluarga->nama_keluarga} berhasil dibuat! Kamu sekarang adalah Kepala Keluarga.");
    }

    /**
     * Preview info keluarga sebelum bergabung dengan kode.
     */
    public function previewCode(string $kode): JsonResponse
    {
        $kodeClean = strtoupper(trim($kode));
        $keluarga = Keluarga::with(['kepalaKeluarga.user', 'anggota'])
            ->where('kode_keluarga', $kodeClean)
            ->first();

        if (! $keluarga) {
            return response()->json([
                'found' => false,
                'message' => 'Kode keluarga tidak valid atau tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'found' => true,
            'id' => $keluarga->id,
            'nama_keluarga' => $keluarga->nama_keluarga,
            'kepala_keluarga' => $keluarga->kepalaKeluarga?->user?->name ?? 'Kepala Keluarga',
            'jumlah_anggota' => $keluarga->anggota->count(),
        ]);
    }

    /**
     * Bergabung ke grup keluarga menggunakan 6 karakter kode.
     */
    public function joinByCode(Request $request): RedirectResponse
    {
        $user = $request->user();
        /** @var Pengguna $pengguna */
        $pengguna = $user->pengguna()->firstOrCreate([]);

        if ($pengguna->keluarga_id) {
            return back()->withErrors([
                'kode_keluarga' => 'Kamu sudah tergabung dalam grup keluarga lain. Keluar terlebih dahulu untuk bergabung ke grup baru.',
            ]);
        }

        $validated = $request->validate([
            'kode_keluarga' => ['required', 'string', 'size:6'],
        ], [
            'kode_keluarga.required' => 'Kode keluarga wajib diisi.',
            'kode_keluarga.size' => 'Kode keluarga harus terdiri dari 6 karakter.',
        ]);

        $kodeClean = strtoupper(trim($validated['kode_keluarga']));
        $keluarga = Keluarga::where('kode_keluarga', $kodeClean)->first();

        if (! $keluarga) {
            return back()->withErrors([
                'kode_keluarga' => 'Kode keluarga tidak ditemukan. Pastikan kode 6 karakter sesuai.',
            ]);
        }

        $pengguna->update([
            'keluarga_id' => $keluarga->id,
        ]);

        return redirect()->route('user.family')->with('success', "Selamat datang di {$keluarga->nama_keluarga}!");
    }

    /**
     * Tampilkan halaman konfirmasi bergabung via share link undangan.
     */
    public function showJoinLink(string $kode, Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $pengguna = $user->pengguna()->firstOrCreate([]);

        $kodeClean = strtoupper(trim($kode));
        $keluarga = Keluarga::with(['kepalaKeluarga.user', 'anggota'])
            ->where('kode_keluarga', $kodeClean)
            ->firstOrFail();

        // Jika sudah tergabung di keluarga ini, langsung redirect ke meja keluarga
        if ($pengguna->keluarga_id === $keluarga->id) {
            return redirect()->route('user.family')->with('info', "Kamu sudah menjadi anggota dari {$keluarga->nama_keluarga}.");
        }

        $currentFamilyName = null;
        if ($pengguna->keluarga_id) {
            $currentFamilyName = $pengguna->keluarga?->nama_keluarga;
        }

        return Inertia::render('User/Keluarga/JoinConfirm', [
            'keluarga' => [
                'id' => $keluarga->id,
                'nama_keluarga' => $keluarga->nama_keluarga,
                'kode_keluarga' => $keluarga->kode_keluarga,
                'kepala_keluarga' => $keluarga->kepalaKeluarga?->user?->name ?? 'Kepala Keluarga',
                'jumlah_anggota' => $keluarga->anggota->count(),
            ],
            'userCurrentFamily' => $currentFamilyName,
        ]);
    }

    /**
     * Eksekusi konfirmasi bergabung melalui link undangan.
     */
    public function acceptJoinLink(string $kode, Request $request): RedirectResponse
    {
        $user = $request->user();
        /** @var Pengguna $pengguna */
        $pengguna = $user->pengguna()->firstOrCreate([]);

        $kodeClean = strtoupper(trim($kode));
        $keluarga = Keluarga::where('kode_keluarga', $kodeClean)->firstOrFail();

        if ($pengguna->keluarga_id && $pengguna->keluarga_id !== $keluarga->id) {
            return redirect()->route('user.family')->with('error', 'Kamu masih tergabung dalam grup keluarga lain. Keluar terlebih dahulu sebelum bergabung.');
        }

        $pengguna->update([
            'keluarga_id' => $keluarga->id,
        ]);

        return redirect()->route('user.family')->with('success', "Kamu berhasil bergabung dengan {$keluarga->nama_keluarga}!");
    }

    /**
     * Tampilkan panel kelola grup untuk Kepala Keluarga.
     */
    public function manage(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $pengguna = $user->pengguna;

        if (! $pengguna?->keluarga_id) {
            return redirect()->route('user.family');
        }

        $keluarga = Keluarga::with(['anggota.user'])->find($pengguna->keluarga_id);

        if (! $keluarga || $keluarga->kepala_keluarga_id !== $pengguna->id) {
            return redirect()->route('user.family')->with('error', 'Hanya Kepala Keluarga yang dapat mengelola grup.');
        }

        $anggotaFormatted = $keluarga->anggota->map(function (Pengguna $m) use ($keluarga) {
            $isHead = ($m->id === $keluarga->kepala_keluarga_id);

            return [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'name' => $m->user?->name ?? 'Anggota',
                'email' => $m->user?->email ?? '',
                'initials' => $this->getInitials($m->user?->name ?? 'A'),
                'is_kepala' => $isHead,
                'role_label' => $isHead ? 'Kepala Keluarga (kamu)' : 'Anggota',
                'usia' => $m->usia,
            ];
        });

        return Inertia::render('User/Keluarga/Manage', [
            'keluarga' => [
                'id' => $keluarga->id,
                'nama_keluarga' => $keluarga->nama_keluarga,
                'kode_keluarga' => $keluarga->kode_keluarga,
                'link_undangan' => route('user.family.join_link', ['kode' => $keluarga->kode_keluarga]),
            ],
            'anggota' => $anggotaFormatted,
        ]);
    }

    /**
     * Perbarui nama grup keluarga.
     */
    public function updateName(Request $request): RedirectResponse
    {
        $user = $request->user();
        $pengguna = $user->pengguna;
        $keluarga = Keluarga::find($pengguna?->keluarga_id);

        if (! $keluarga || $keluarga->kepala_keluarga_id !== $pengguna?->id) {
            abort(403, 'Aksi tidak diizinkan.');
        }

        $validated = $request->validate([
            'nama_keluarga' => [
                'required',
                'string',
                'min:3',
                'max:40',
                Rule::unique('keluarga', 'nama_keluarga')->ignore($keluarga->id),
            ],
        ], [
            'nama_keluarga.required' => 'Nama keluarga wajib diisi.',
            'nama_keluarga.min' => 'Nama keluarga minimal 3 karakter.',
            'nama_keluarga.max' => 'Nama keluarga maksimal 40 karakter.',
            'nama_keluarga.unique' => 'Nama keluarga ini sudah dipakai.',
        ]);

        $keluarga->update([
            'nama_keluarga' => $validated['nama_keluarga'],
        ]);

        return back()->with('success', 'Nama grup keluarga berhasil diperbarui.');
    }

    /**
     * Regenerasi kode grup 6 karakter.
     */
    public function regenerateCode(Request $request): RedirectResponse
    {
        $user = $request->user();
        $pengguna = $user->pengguna;
        $keluarga = Keluarga::find($pengguna?->keluarga_id);

        if (! $keluarga || $keluarga->kepala_keluarga_id !== $pengguna?->id) {
            abort(403, 'Aksi tidak diizinkan.');
        }

        $newCode = Keluarga::generateKodeKeluarga();
        $keluarga->update(['kode_keluarga' => $newCode]);

        return back()->with('success', "Kode baru berhasil dibuat: {$newCode}. Kode lama tidak lagi berlaku.");
    }

    /**
     * Alihkan peran Kepala Keluarga ke anggota lain (pilihan dari dropdown).
     */
    public function transferHead(Request $request): RedirectResponse
    {
        $user = $request->user();
        $pengguna = $user->pengguna;
        $keluarga = Keluarga::find($pengguna?->keluarga_id);

        if (! $keluarga || $keluarga->kepala_keluarga_id !== $pengguna?->id) {
            abort(403, 'Aksi tidak diizinkan.');
        }

        $validated = $request->validate([
            'new_head_id' => [
                'required',
                'integer',
                Rule::exists('pengguna', 'id')->where('keluarga_id', $keluarga->id),
            ],
        ], [
            'new_head_id.required' => 'Silakan pilih anggota keluarga yang akan dijadikan Kepala Keluarga.',
            'new_head_id.exists' => 'Anggota yang dipilih tidak valid atau bukan bagian dari keluarga ini.',
        ]);

        if ((int) $validated['new_head_id'] === $pengguna->id) {
            return back()->withErrors(['new_head_id' => 'Kamu sudah menjadi Kepala Keluarga saat ini.']);
        }

        $keluarga->update([
            'kepala_keluarga_id' => $validated['new_head_id'],
        ]);

        $newHeadUser = Pengguna::with('user')->find($validated['new_head_id']);
        $newHeadName = $newHeadUser?->user?->name ?? 'Anggota';

        return redirect()->route('user.family')->with('success', "Peran Kepala Keluarga berhasil dialihkan kepada {$newHeadName}. Kamu sekarang berstatus sebagai Anggota.");
    }

    /**
     * Keluarkan anggota dari grup keluarga.
     */
    public function removeMember(Pengguna $member, Request $request): RedirectResponse
    {
        $user = $request->user();
        $pengguna = $user->pengguna;
        $keluarga = Keluarga::find($pengguna?->keluarga_id);

        if (! $keluarga || $keluarga->kepala_keluarga_id !== $pengguna?->id) {
            abort(403, 'Aksi tidak diizinkan.');
        }

        if ($member->keluarga_id !== $keluarga->id) {
            return back()->with('error', 'Anggota tidak ditemukan di keluarga ini.');
        }

        if ($member->id === $pengguna->id) {
            return back()->with('error', 'Kepala Keluarga tidak bisa mengeluarkan diri sendiri. Alihkan peran terlebih dahulu.');
        }

        $memberName = $member->user?->name ?? 'Anggota';
        $member->update(['keluarga_id' => null]);

        return back()->with('success', "{$memberName} berhasil dikeluarkan dari grup.");
    }

    /**
     * Hapus / Bubarkan grup keluarga.
     */
    public function deleteGroup(Request $request): RedirectResponse
    {
        $user = $request->user();
        $pengguna = $user->pengguna;
        $keluarga = Keluarga::find($pengguna?->keluarga_id);

        if (! $keluarga || $keluarga->kepala_keluarga_id !== $pengguna?->id) {
            abort(403, 'Aksi tidak diizinkan.');
        }

        $namaGrup = $keluarga->nama_keluarga;

        // Lepaskan seluruh anggota
        Pengguna::where('keluarga_id', $keluarga->id)->update(['keluarga_id' => null]);

        $keluarga->delete();

        return redirect()->route('user.family')->with('success', "Grup {$namaGrup} telah berhasil dihapus.");
    }

    /**
     * Anggota keluar dari grup keluarga secara mandiri.
     */
    public function leaveGroup(Request $request): RedirectResponse
    {
        $user = $request->user();
        $pengguna = $user->pengguna;
        $keluarga = Keluarga::find($pengguna?->keluarga_id);

        if (! $keluarga) {
            return redirect()->route('user.family');
        }

        // Jika kepala keluarga dan masih ada anggota lain, tolak
        $otherMembersCount = Pengguna::where('keluarga_id', $keluarga->id)->where('id', '!=', $pengguna->id)->count();

        if ($keluarga->kepala_keluarga_id === $pengguna->id && $otherMembersCount > 0) {
            return back()->with('error', 'Kamu adalah Kepala Keluarga. Alihkan peran Kepala Keluarga ke anggota lain terlebih dahulu sebelum keluar, atau hapus grup.');
        }

        // Jika satu-satunya anggota
        if ($otherMembersCount === 0) {
            $pengguna->update(['keluarga_id' => null]);
            $keluarga->delete();

            return redirect()->route('user.family')->with('success', 'Kamu telah keluar dan grup telah dibubarkan.');
        }

        $pengguna->update(['keluarga_id' => null]);

        return redirect()->route('user.family')->with('success', "Kamu telah keluar dari {$keluarga->nama_keluarga}.");
    }

    /**
     * Helper inisial nama.
     */
    protected function getInitials(string $name): string
    {
        $parts = explode(' ', trim($name));
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_substr($part, 0, 1);
        }

        return strtoupper($initials ?: 'U');
    }
}
