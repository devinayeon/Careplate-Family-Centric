<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Artikel;
use App\Models\KategoriArtikel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminArticleCategoryController extends Controller
{
    /**
     * Ambil semua daftar kategori artikel beserta jumlah artikel yang menggunakannya.
     */
    public function index(): JsonResponse
    {
        $categories = KategoriArtikel::withCount('artikels')
            ->orderBy('nama', 'asc')
            ->get();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    /**
     * Tambah kategori baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:100|unique:kategori_artikel,nama',
            'deskripsi' => 'nullable|string|max:255',
        ]);

        $validated['slug'] = Str::slug($validated['nama']);

        $category = KategoriArtikel::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil ditambahkan!',
            'category' => $category->loadCount('artikels'),
        ], 201);
    }

    /**
     * Perbarui nama kategori.
     */
    public function update(Request $request, KategoriArtikel $kategoriArtikel): JsonResponse
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:100|unique:kategori_artikel,nama,' . $kategoriArtikel->id,
            'deskripsi' => 'nullable|string|max:255',
        ]);

        $oldName = $kategoriArtikel->nama;
        $newName = $validated['nama'];

        $validated['slug'] = Str::slug($newName);
        $kategoriArtikel->update($validated);

        // Jika nama kategori berubah, sinkronkan juga artikel yang menggunakan nama lama
        if ($oldName !== $newName) {
            Artikel::where('kategori', $oldName)->update(['kategori' => $newName]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil diperbarui!',
            'category' => $kategoriArtikel->fresh()->loadCount('artikels'),
        ]);
    }

    /**
     * Hapus kategori.
     */
    public function destroy(KategoriArtikel $kategoriArtikel): JsonResponse
    {
        $count = $kategoriArtikel->artikels()->count();

        if ($count > 0) {
            return response()->json([
                'success' => false,
                'message' => "Kategori '{$kategoriArtikel->nama}' tidak dapat dihapus karena masih digunakan oleh {$count} artikel. Silakan ubah kategori artikel tersebut terlebih dahulu.",
            ], 422);
        }

        $kategoriArtikel->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dihapus!',
        ]);
    }
}
