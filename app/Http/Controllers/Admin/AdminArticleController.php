<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Artikel;
use App\Models\KategoriArtikel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminArticleController extends Controller
{
    /**
     * Tampilkan daftar artikel dengan filter & search.
     */
    public function index(Request $request): Response
    {
        $status = $request->query('status', 'semua');
        $search = $request->query('search', '');

        $query = Artikel::query()->filter([
            'status' => $status,
            'search' => $search,
        ])->latest('updated_at');

        $artikels = $query->paginate(10)->withQueryString()->through(function (Artikel $artikel) {
            return [
                'id' => $artikel->id,
                'judul' => $artikel->judul,
                'slug' => $artikel->slug,
                'ringkasan' => $artikel->ringkasan,
                'kategori' => $artikel->kategori,
                'foto_sampul_url' => $artikel->foto_sampul_url,
                'nama_penulis' => $artikel->nama_penulis ?? 'Tim Redaksi',
                'status' => $artikel->status,
                'views' => $artikel->views,
                'diperbarui' => $artikel->updated_at?->translatedFormat('d M Y') ?? $artikel->created_at?->translatedFormat('d M Y'),
                'published_at' => $artikel->published_at?->translatedFormat('d M Y H:i'),
            ];
        });

        // Hitung total untuk badge filter tabs
        $totalSemua = Artikel::count();
        $totalTerbit = Artikel::where('status', 'terbit')->count();
        $totalDraf = Artikel::where('status', 'draf')->count();

        return Inertia::render('Admin/Artikel/Index', [
            'artikels' => $artikels,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'counts' => [
                'semua' => $totalSemua,
                'terbit' => $totalTerbit,
                'draf' => $totalDraf,
            ],
        ]);
    }

    /**
     * Tampilkan formulir editor pembuatan artikel baru.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Artikel/Editor', [
            'mode' => 'create',
            'artikel' => null,
            'kategoriOptions' => $this->getKategoriOptions(),
        ]);
    }

    /**
     * Simpan artikel baru ke database.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:artikel,slug',
            'kategori' => 'required|string|max:100',
            'ringkasan' => 'nullable|string|max:500',
            'konten' => 'required|string',
            'nama_penulis' => 'nullable|string|max:255',
            'status' => 'required|in:draf,terbit',
            'foto_sampul' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:3072',
        ]);

        // Auto generate slug jika tidak diisi
        $slug = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['judul']);
        $originalSlug = $slug;
        $counter = 1;
        while (Artikel::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }
        $validated['slug'] = $slug;

        // Upload foto sampul jika ada
        if ($request->hasFile('foto_sampul')) {
            $path = $request->file('foto_sampul')->store('artikel/sampul', 'public');
            $validated['foto_sampul'] = $path;
        }

        $validated['penulis_id'] = $request->user()?->id;
        $validated['nama_penulis'] = $validated['nama_penulis'] ?: ($request->user()?->name ?? 'Tim Redaksi');

        if ($validated['status'] === 'terbit') {
            $validated['published_at'] = now();
        }

        Artikel::create($validated);

        return redirect()->route('admin.articles')->with('success', 'Artikel berhasil disimpan!');
    }

    /**
     * Tampilkan formulir edit artikel.
     */
    public function edit(Artikel $artikel): Response
    {
        return Inertia::render('Admin/Artikel/Editor', [
            'mode' => 'edit',
            'artikel' => [
                'id' => $artikel->id,
                'judul' => $artikel->judul,
                'slug' => $artikel->slug,
                'ringkasan' => $artikel->ringkasan,
                'konten' => $artikel->konten,
                'kategori' => $artikel->kategori,
                'foto_sampul_url' => $artikel->foto_sampul_url,
                'nama_penulis' => $artikel->nama_penulis,
                'status' => $artikel->status,
                'published_at' => $artikel->published_at?->format('Y-m-d H:i:s'),
            ],
            'kategoriOptions' => $this->getKategoriOptions(),
        ]);
    }

    /**
     * Update artikel yang ada di database.
     */
    public function update(Request $request, Artikel $artikel): RedirectResponse
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:artikel,slug,'.$artikel->id,
            'kategori' => 'required|string|max:100',
            'ringkasan' => 'nullable|string|max:500',
            'konten' => 'required|string',
            'nama_penulis' => 'nullable|string|max:255',
            'status' => 'required|in:draf,terbit',
            'foto_sampul' => 'nullable',
        ]);

        // Auto generate slug jika berubah
        if (! empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['slug']);
        } else {
            $validated['slug'] = Str::slug($validated['judul']);
        }

        // Upload foto sampul jika ada file baru
        if ($request->hasFile('foto_sampul')) {
            $request->validate([
                'foto_sampul' => 'image|mimes:jpeg,png,jpg,webp|max:3072',
            ]);

            // Hapus file lama jika disimpan di storage lokal
            if ($artikel->foto_sampul && ! Str::startsWith($artikel->foto_sampul, ['http://', 'https://'])) {
                Storage::disk('public')->delete($artikel->foto_sampul);
            }

            $path = $request->file('foto_sampul')->store('artikel/sampul', 'public');
            $validated['foto_sampul'] = $path;
        } else {
            // Pertahankan foto sampul lama jika tidak diganti
            unset($validated['foto_sampul']);
        }

        // Atur waktu terbit jika baru diterbitkan
        if ($validated['status'] === 'terbit' && ! $artikel->published_at) {
            $validated['published_at'] = now();
        }

        $artikel->update($validated);

        return redirect()->route('admin.articles')->with('success', 'Artikel berhasil diperbarui!');
    }

    /**
     * Hapus artikel dari database.
     */
    public function destroy(Artikel $artikel): RedirectResponse
    {
        if ($artikel->foto_sampul && ! Str::startsWith($artikel->foto_sampul, ['http://', 'https://'])) {
            Storage::disk('public')->delete($artikel->foto_sampul);
        }

        $artikel->delete();

        return redirect()->route('admin.articles')->with('success', 'Artikel berhasil dihapus!');
    }

    /**
     * Endpoint upload gambar inline langsung dari TipTap Editor.
     */
    public function uploadEditorImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        $path = $request->file('image')->store('artikel/inline', 'public');

        return response()->json([
            'url' => asset('storage/'.$path),
        ]);
    }

    /**
     * Daftar kategori pilihan artikel CarePlate dari database.
     *
     * @return array<int, string>
     */
    protected function getKategoriOptions(): array
    {
        $categories = KategoriArtikel::orderBy('nama', 'asc')->pluck('nama')->toArray();

        if (empty($categories)) {
            return [
                'Gizi Keluarga',
                'Gizi Anak',
                'Label Kemasan',
                'Tips Sehat',
                'Pola Makan',
                'Resep Sehat',
            ];
        }

        return $categories;
    }
}
