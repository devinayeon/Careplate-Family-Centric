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

class AdminFoodController extends Controller
{
    /**
     * Tampilkan tabel data makanan admin dengan pencarian dan filter status.
     */
    public function index(Request $request): Response
    {
        $status = $request->query('status', 'semua');
        $search = $request->query('search', '');

        $query = Makanan::with('kategoriMakanan', 'pengaju')
            ->filter([
                'status' => $status,
                'search' => $search,
            ])
            ->latest('id');

        $page = $query->paginate(10)->withQueryString();
        $aiStatus = AiFoodStatus::forMany(collect($page->items()));

        $foods = $page->through(function (Makanan $item) use ($aiStatus) {
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
                'status' => $item->status ?? 'terverifikasi',
                'pengaju' => $item->nama_pengaju ?: ($item->pengaju?->name ?? 'Admin'),
                'foto_makanan_url' => $item->foto_makanan_url,
                'ai_status' => $aiStatus[$item->id] ?? null,
            ];
        });

        // Hitung total untuk tabs status
        $totalSemua = Makanan::count();
        $totalTerverifikasi = Makanan::where('status', 'terverifikasi')->count();
        $totalMenunggu = Makanan::where('status', 'menunggu')->count();
        $totalDitolak = Makanan::where('status', 'ditolak')->count();

        $categories = KategoriMakanan::orderBy('kategori', 'asc')->get(['id', 'kategori']);

        return Inertia::render('Admin/Makanan/Index', [
            'foods' => $foods,
            'categories' => $categories,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'counts' => [
                'semua' => $totalSemua,
                'terverifikasi' => $totalTerverifikasi,
                'menunggu' => $totalMenunggu,
                'ditolak' => $totalDitolak,
            ],
        ]);
    }

    /**
     * Simpan data makanan baru.
     */
    public function store(Request $request): RedirectResponse
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
            'status' => 'nullable|in:terverifikasi,menunggu,ditolak',
            'foto_makanan' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:3072',
        ]);

        if ($request->hasFile('foto_makanan')) {
            $path = $request->file('foto_makanan')->store('makanan', 'public');
            $validated['foto_makanan'] = $path;
        }

        $validated['pengaju_id'] = $request->user()?->id;
        $validated['nama_pengaju'] = 'Admin';
        $validated['status'] = $validated['status'] ?? 'terverifikasi';
        $validated['sumber'] = $validated['sumber'] ?: 'Tabel Komposisi Pangan';
        $validated['gula'] = $validated['gula'] ?? 0;
        $validated['natrium'] = $validated['natrium'] ?? 0;

        Makanan::create($validated);

        return redirect()->route('admin.foods')->with('success', 'Data makanan berhasil ditambahkan!');
    }

    /**
     * Perbarui data makanan yang ada.
     */
    public function update(Request $request, Makanan $makanan): RedirectResponse
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
            'status' => 'nullable|in:terverifikasi,menunggu,ditolak',
            'foto_makanan' => 'nullable',
        ]);

        if ($request->hasFile('foto_makanan')) {
            $request->validate([
                'foto_makanan' => 'image|mimes:jpeg,png,jpg,webp|max:3072',
            ]);

            // Hapus foto lokal lama jika ada
            if ($makanan->foto_makanan && ! Str::startsWith($makanan->foto_makanan, ['http://', 'https://'])) {
                Storage::disk('public')->delete($makanan->foto_makanan);
            }

            $path = $request->file('foto_makanan')->store('makanan', 'public');
            $validated['foto_makanan'] = $path;
        } else {
            // Jangan timpa jika tidak ada foto baru diunggah
            unset($validated['foto_makanan']);
        }

        $validated['gula'] = $validated['gula'] ?? 0;
        $validated['natrium'] = $validated['natrium'] ?? 0;

        $makanan->update($validated);

        return redirect()->route('admin.foods')->with('success', 'Data makanan berhasil diperbarui!');
    }

    /**
     * Hapus data makanan (Soft Delete).
     * Data tidak terhapus fisik dari database agar log konsumsi historis pengguna tetap aman.
     */
    public function destroy(Makanan $makanan): RedirectResponse
    {
        $makanan->delete();

        return redirect()->route('admin.foods')->with('success', 'Data makanan berhasil dihapus (Soft Delete)!');
    }
}
