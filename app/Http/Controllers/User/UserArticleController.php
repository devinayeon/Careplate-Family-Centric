<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Artikel;
use App\Models\KategoriArtikel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserArticleController extends Controller
{
    /**
     * Tampilkan katalog artikel untuk pengguna dengan kategori, search, dan artikel unggulan.
     */
    public function index(Request $request): Response
    {
        $kategori = $request->query('kategori');
        $search = trim((string) $request->query('search', ''));
        $page = (int) $request->query('page', 1);

        // Kategori dinamis dari database
        $categories = KategoriArtikel::orderBy('nama')->get(['id', 'nama', 'slug']);

        // Base query untuk artikel yang sudah berstatus 'terbit'
        $baseQuery = Artikel::query()
            ->where('status', 'terbit')
            ->latest('published_at')
            ->latest('id');

        if ($kategori && $kategori !== 'semua') {
            $baseQuery->where('kategori', $kategori);
        }

        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                    ->orWhere('ringkasan', 'like', "%{$search}%")
                    ->orWhere('konten', 'like', "%{$search}%")
                    ->orWhere('kategori', 'like', "%{$search}%")
                    ->orWhere('nama_penulis', 'like', "%{$search}%");
            });
        }

        $featuredArticle = null;

        // Pada halaman pertama dan tanpa filter pencarian spesifik, pisahkan 1 artikel teratas sebagai Featured
        if ($page <= 1 && $search === '') {
            $featuredModel = (clone $baseQuery)->first();

            if ($featuredModel) {
                $featuredArticle = $this->transformArticle($featuredModel);
                $articlesQuery = (clone $baseQuery)->where('id', '!=', $featuredModel->id);
                $articles = $articlesQuery->paginate(6)->withQueryString();
            } else {
                $articles = $baseQuery->paginate(6)->withQueryString();
            }
        } else {
            // Jika dalam mode pencarian atau halaman ke-2 dst, tampilkan semua secara seragam dalam grid
            $articles = $baseQuery->paginate(9)->withQueryString();
        }

        $articles->through(fn (Artikel $item) => $this->transformArticle($item));

        return Inertia::render('User/Artikel/Index', [
            'featuredArticle' => $featuredArticle,
            'articles' => $articles,
            'categories' => $categories,
            'filters' => [
                'kategori' => $kategori ?: 'semua',
                'search' => $search,
            ],
        ]);
    }

    /**
     * Tampilkan detail artikel berdasarkan slug.
     */
    public function show(string $slug): Response
    {
        $article = Artikel::query()
            ->where('slug', $slug)
            ->where('status', 'terbit')
            ->firstOrFail();

        // Tingkatkan view counter secara atomik
        $article->increment('views');

        // Ambil rekomendasi artikel terkait (prioritas kategori sama, jika kurang ambil artikel terbit lainnya)
        $relatedArticles = Artikel::query()
            ->where('status', 'terbit')
            ->where('id', '!=', $article->id)
            ->where(function ($q) use ($article) {
                $q->where('kategori', $article->kategori)
                    ->orWhereNotNull('id');
            })
            ->latest('published_at')
            ->take(3)
            ->get()
            ->map(fn (Artikel $item) => $this->transformArticle($item));

        return Inertia::render('User/Artikel/Show', [
            'article' => [
                'id' => $article->id,
                'judul' => $article->judul,
                'slug' => $article->slug,
                'ringkasan' => $article->ringkasan,
                'konten' => $article->konten,
                'kategori' => $article->kategori,
                'foto_sampul_url' => $article->foto_sampul_url,
                'nama_penulis' => $article->nama_penulis ?: 'Tim Redaksi CarePlate',
                'waktu_baca' => $article->waktu_baca,
                'tanggal_terbit' => $article->tanggal_terbit,
                'views' => $article->views,
            ],
            'relatedArticles' => $relatedArticles,
        ]);
    }

    /**
     * Format data artikel untuk tampilan antarmuka.
     *
     * @return array<string, mixed>
     */
    protected function transformArticle(Artikel $article): array
    {
        return [
            'id' => $article->id,
            'judul' => $article->judul,
            'slug' => $article->slug,
            'ringkasan' => $article->ringkasan,
            'kategori' => $article->kategori,
            'foto_sampul_url' => $article->foto_sampul_url,
            'nama_penulis' => $article->nama_penulis ?: 'Tim Redaksi CarePlate',
            'waktu_baca' => $article->waktu_baca,
            'tanggal_terbit' => $article->tanggal_terbit,
            'views' => $article->views,
        ];
    }
}
