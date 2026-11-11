<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KategoriMakanan;
use App\Models\Makanan;
use App\Services\Ai\Foods\AiFoodStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminVerificationController extends Controller
{
    /**
     * Tampilkan halaman verifikasi pengajuan makanan.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        // Default filter status: 'menunggu' (sesuai permintaan user: tanpa filter 'semua', pertama kali dibuka adalah 'menunggu')
        $status = $request->query('status', 'menunggu');
        if (! in_array($status, ['menunggu', 'terverifikasi', 'ditolak'])) {
            $status = 'menunggu';
        }

        $search = $request->query('search', '');

        // Query antrean pengajuan (urut dari yang terlama / oldest ID agar antrean yang duluan diajukan diproses lebih dulu)
        $query = Makanan::with('kategoriMakanan', 'pengaju', 'verifikator')
            ->where('status', $status)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('nama', 'like', "%{$search}%")
                        ->orWhere('nama_pengaju', 'like', "%{$search}%")
                        ->orWhereHas('kategoriMakanan', function ($qk) use ($search) {
                            $qk->where('kategori', 'like', "%{$search}%");
                        });
                });
            })
            ->oldest('created_at');

        $submissions = $query->paginate(6)->appends($request->except('selected'))->through(function (Makanan $item) {
            $formattedTakaran = rtrim(rtrim(number_format((float) $item->kuantitas, 2, '.', ''), '0'), '.');
            if ($formattedTakaran === '') {
                $formattedTakaran = '1';
            }

            return [
                'id' => $item->id,
                'nama' => $item->nama,
                'kuantitas' => (float) $item->kuantitas,
                'satuan' => $item->satuan,
                'berat_per_satuan_gram' => $item->berat_per_satuan_gram === null ? null : (float) $item->berat_per_satuan_gram,
                'takaran_saji' => "{$formattedTakaran} {$item->satuan}",
                'kategori' => $item->kategoriMakanan?->kategori ?? 'Umum',
                'kategori_makanan_id' => $item->kategori_makanan_id,
                'energi' => (float) $item->energi,
                'protein' => (float) $item->protein,
                'karbohidrat' => (float) $item->karbohidrat,
                'lemak' => (float) $item->lemak,
                'gula' => (float) ($item->gula ?? 0),
                'natrium' => (float) ($item->natrium ?? 0),
                'sumber' => $item->sumber ?? 'Tabel Komposisi Pangan',
                'status' => $item->status,
                'pengaju' => $item->nama_pengaju ?: ($item->pengaju?->name ?? 'Admin'),
                'foto_makanan_url' => $item->foto_makanan_url,
                'alasan_penolakan' => $item->alasan_penolakan,
                'waktu_pengajuan' => $item->created_at?->diffForHumans() ?? 'Baru saja',
                'tanggal_lengkap' => $item->created_at?->translatedFormat('d M Y, H.i') ?? '',
            ];
        });

        // Item terpilih untuk panel detail
        $selectedId = $request->query('selected');
        $selectedItem = null;

        $currentPageIds = $submissions->pluck('id')->all();

        // Cek apakah item yang diminta ada di dalam antrean halaman saat ini dengan status yang sesuai
        if ($selectedId && in_array((int) $selectedId, array_map('intval', $currentPageIds), true)) {
            $selectedModel = Makanan::with('kategoriMakanan', 'pengaju')
                ->where('status', $status)
                ->find($selectedId);
            if ($selectedModel) {
                $selectedItem = $this->transformFoodItem($selectedModel);
            }
        }

        // Fallback jika belum memilih item atau item terpilih tidak berada di halaman saat ini
        if (! $selectedItem && count($currentPageIds) > 0) {
            $selectedModel = Makanan::with('kategoriMakanan', 'pengaju')->find($currentPageIds[0]);
            if ($selectedModel) {
                $selectedItem = $this->transformFoodItem($selectedModel);
            }
        }

        // Analisis cerdas untuk selected item
        $atwaterCheck = null;
        $similarFood = null;

        if ($selectedItem) {
            // 1. Rumus Atwater Energy Check: 4xP + 4xC + 9xF
            $p = (float) $selectedItem['protein'];
            $c = (float) $selectedItem['karbohidrat'];
            $f = (float) $selectedItem['lemak'];
            $totalHitung = ($p * 4) + ($c * 4) + ($f * 9);
            $energiInput = (float) $selectedItem['energi'];

            $selisih = abs($energiInput - $totalHitung);
            $persenSelisih = $energiInput > 0 ? round(($selisih / $energiInput) * 100, 1) : 0;

            $atwaterCheck = [
                'protein' => $p,
                'karbohidrat' => $c,
                'lemak' => $f,
                'energi_hitung' => round($totalHitung),
                'energi_input' => round($energiInput),
                'persen_selisih' => $persenSelisih,
                'formula_text' => "4×{$p} + 4×{$c} + 9×{$f} = ".round($totalHitung)." kkal, selisih {$persenSelisih}% dari ".round($energiInput).' kkal.',
            ];

            // 2. Deteksi Makanan Serupa di Database
            $similar = Makanan::where('status', 'terverifikasi')
                ->where('id', '!=', $selectedItem['id'])
                ->where(function ($q) use ($selectedItem) {
                    $q->where('nama', 'like', '%'.Str::words($selectedItem['nama'], 2, '').'%')
                        ->orWhere('kategori_makanan_id', $selectedItem['kategori_makanan_id']);
                })
                ->first();

            if ($similar) {
                $similarFormattedTakaran = rtrim(rtrim(number_format((float) $similar->kuantitas, 2, '.', ''), '0'), '.');
                $similarFood = [
                    'id' => $similar->id,
                    'nama' => $similar->nama,
                    'energi' => round((float) $similar->energi),
                    'takaran' => "{$similarFormattedTakaran} {$similar->satuan}",
                    'text' => "Mirip dengan \"{$similar->nama}\" (".round((float) $similar->energi)." kkal per {$similarFormattedTakaran} {$similar->satuan}) di database.",
                ];
            }
        }

        // Hitung badge counter untuk masing-masing tab
        $counts = [
            'menunggu' => Makanan::where('status', 'menunggu')->count(),
            'terverifikasi' => Makanan::where('status', 'terverifikasi')->count(),
            'ditolak' => Makanan::where('status', 'ditolak')->count(),
        ];

        $categories = KategoriMakanan::orderBy('kategori', 'asc')->get(['id', 'kategori']);

        return Inertia::render('Admin/Verifikasi/Index', [
            'submissions' => $submissions,
            'selectedItem' => $selectedItem,
            'atwaterCheck' => $atwaterCheck,
            'similarFood' => $similarFood,
            'categories' => $categories,
            'filters' => [
                'status' => $status,
                'search' => $search,
                'selected' => $selectedItem ? $selectedItem['id'] : null,
                'page' => $submissions->currentPage(),
            ],
            'counts' => $counts,
        ]);
    }

    /**
     * Langsung menyetujui (Verifikasi) pengajuan makanan.
     */
    public function approve(Request $request, Makanan $makanan): RedirectResponse
    {
        $makanan->update([
            'status' => 'terverifikasi',
            'alasan_penolakan' => null,
            'diverifikasi_oleh' => $request->user()?->id,
            'diverifikasi_pada' => now(),
        ]);

        return redirect()->back()->with('success', "Pengajuan '{$makanan->nama}' berhasil diverifikasi dan tersinkron ke katalog!");
    }

    /**
     * Menolak pengajuan makanan dengan alasan penolakan.
     */
    public function reject(Request $request, Makanan $makanan): RedirectResponse
    {
        $validated = $request->validate([
            'alasan_penolakan' => 'required|string|max:1000',
        ], [
            'alasan_penolakan.required' => 'Alasan penolakan wajib diisi agar pengaju dapat mengetahui hal yang perlu diperbaiki.',
        ]);

        $makanan->update([
            'status' => 'ditolak',
            'alasan_penolakan' => $validated['alasan_penolakan'],
            'diverifikasi_oleh' => $request->user()?->id,
            'diverifikasi_pada' => now(),
        ]);

        return redirect()->back()->with('success', "Pengajuan '{$makanan->nama}' berhasil ditolak. Alasan penolakan telah disimpan.");
    }

    /**
     * Ubah data makanan lalu langsung verifikasi.
     */
    public function updateAndApprove(Request $request, Makanan $makanan): RedirectResponse
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'kategori_makanan_id' => 'required|exists:kategori_makanan,id',
            'kuantitas' => 'required|numeric|min:0.01',
            'satuan' => 'required|string|max:50',
            'berat_per_satuan_gram' => 'nullable|numeric|gt:0|max:100000',
            'energi' => 'required|numeric|min:0',
            'protein' => 'required|numeric|min:0',
            'karbohidrat' => 'required|numeric|min:0',
            'lemak' => 'required|numeric|min:0',
            'gula' => 'nullable|numeric|min:0',
            'natrium' => 'nullable|numeric|min:0',
            'sumber' => 'nullable|string|max:150',
            'foto_makanan' => 'nullable',
        ]);

        if ($request->hasFile('foto_makanan')) {
            $request->validate([
                'foto_makanan' => 'image|mimes:jpeg,png,jpg,webp|max:3072',
            ]);

            if ($makanan->foto_makanan && ! Str::startsWith($makanan->foto_makanan, ['http://', 'https://'])) {
                Storage::disk('public')->delete($makanan->foto_makanan);
            }

            $path = $request->file('foto_makanan')->store('makanan', 'public');
            $validated['foto_makanan'] = $path;
        } else {
            unset($validated['foto_makanan']);
        }

        $validated['status'] = 'terverifikasi';
        $validated['alasan_penolakan'] = null;
        $validated['diverifikasi_oleh'] = $request->user()?->id;
        $validated['diverifikasi_pada'] = now();
        $validated['gula'] = $validated['gula'] ?? 0;
        $validated['natrium'] = $validated['natrium'] ?? 0;

        $makanan->update($validated);

        return redirect()->back()->with('success', "Data '{$makanan->nama}' berhasil diperbarui dan diverifikasi!");
    }

    /**
     * Helper transform model makanan ke array representasi.
     *
     * @return array<string, mixed>
     */
    protected function transformFoodItem(Makanan $item): array
    {
        $formattedTakaran = rtrim(rtrim(number_format((float) $item->kuantitas, 2, '.', ''), '0'), '.');
        if ($formattedTakaran === '') {
            $formattedTakaran = '1';
        }

        return [
            'id' => $item->id,
            'nama' => $item->nama,
            'kuantitas' => (float) $item->kuantitas,
            'satuan' => $item->satuan,
            'berat_per_satuan_gram' => $item->berat_per_satuan_gram === null ? null : (float) $item->berat_per_satuan_gram,
            'takaran_saji' => "{$formattedTakaran} {$item->satuan}",
            'kategori' => $item->kategoriMakanan?->kategori ?? 'Umum',
            'kategori_makanan_id' => $item->kategori_makanan_id,
            'energi' => (float) $item->energi,
            'protein' => (float) $item->protein,
            'karbohidrat' => (float) $item->karbohidrat,
            'lemak' => (float) $item->lemak,
            'gula' => (float) ($item->gula ?? 0),
            'natrium' => (float) ($item->natrium ?? 0),
            'sumber' => $item->sumber ?? 'Tabel Komposisi Pangan',
            'status' => $item->status,
            'pengaju' => $item->nama_pengaju ?: ($item->pengaju?->name ?? 'Admin'),
            'foto_makanan_url' => $item->foto_makanan_url,
            'alasan_penolakan' => $item->alasan_penolakan,
            'waktu_pengajuan' => $item->created_at?->diffForHumans() ?? 'Baru saja',
            'tanggal_lengkap' => $item->created_at?->translatedFormat('d M Y, H.i') ?? '',
            'ai_status' => AiFoodStatus::forMany(collect([$item]))[$item->id],
        ];
    }
}
