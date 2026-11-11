<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\KategoriMakanan;
use App\Models\Makanan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * User side of "Data Makanan" (Figma 07): browse verified foods, submit a new one, follow the review.
 * A submission is always `menunggu` and owned by the submitter; only an admin verifies it (AdminVerificationController),
 * and a verified gram food reaches the AI chat through MakananObserver.
 */
class UserFoodController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $category = $request->query('kategori');

        $foods = Makanan::query()
            ->where('status', 'terverifikasi')
            ->when($search !== '', fn ($q) => $q->where('nama', 'like', '%'.$search.'%'))
            ->when(is_numeric($category), fn ($q) => $q->where('kategori_makanan_id', (int) $category))
            ->orderBy('nama')
            ->paginate(12)->withQueryString()
            ->through(fn (Makanan $m) => self::card($m));

        $counts = Makanan::query()->where('status', 'terverifikasi')->selectRaw('kategori_makanan_id, count(*) as total')
            ->groupBy('kategori_makanan_id')->pluck('total', 'kategori_makanan_id');

        return Inertia::render('User/Makanan/Index', [
            'foods' => $foods,
            'categories' => KategoriMakanan::orderBy('kategori')->get(['id', 'kategori'])
                ->map(fn ($c) => ['id' => $c->id, 'kategori' => $c->kategori, 'total' => (int) ($counts[$c->id] ?? 0)]),
            'filters' => ['search' => $search, 'kategori' => is_numeric($category) ? (int) $category : null],
        ]);
    }

    public function show(Request $request, Makanan $makanan): Response
    {
        // Verified foods are public to signed-in users; a waiting or rejected one only to its submitter.
        abort_unless($makanan->status === 'terverifikasi' || $makanan->pengaju_id === $request->user()->id, 404);

        return Inertia::render('User/Makanan/Show', [
            'food' => self::card($makanan) + [
                'lemak' => (float) $makanan->lemak, 'karbohidrat' => (float) $makanan->karbohidrat,
                'gula' => (float) $makanan->gula, 'natrium' => (float) $makanan->natrium,
                'sumber' => $makanan->sumber, 'kategori' => $makanan->kategoriMakanan?->kategori,
                'diverifikasi_pada' => $makanan->diverifikasi_pada?->toDateString(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('User/Makanan/Ajukan', [
            'categories' => KategoriMakanan::orderBy('kategori')->get(['id', 'kategori']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Only these fields are read: status, owner and review fields are set here, never taken from the request.
        $data = $request->validate([
            'nama' => 'required|string|max:100',
            'kategori_makanan_id' => 'required|exists:kategori_makanan,id',
            'kuantitas' => 'required|numeric|gt:0|max:100000',
            'satuan' => 'required|in:g,ml',
            'energi' => 'required|numeric|min:0|max:100000',
            'protein' => 'required|numeric|min:0|max:100000',
            'karbohidrat' => 'required|numeric|min:0|max:100000',
            'lemak' => 'required|numeric|min:0|max:100000',
            'gula' => 'nullable|numeric|min:0|max:100000',
            'natrium' => 'nullable|numeric|min:0|max:1000000',
            'sumber' => 'required|string|max:150',
            'foto_makanan' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:3072',
        ]);

        if ($request->hasFile('foto_makanan')) {
            $data['foto_makanan'] = $request->file('foto_makanan')->store('makanan', 'public');
        }

        Makanan::create($data + [
            'gula' => 0, 'natrium' => 0,
            'status' => 'menunggu', 'pengaju_id' => $request->user()->id, 'nama_pengaju' => $request->user()->name,
        ]);

        return redirect()->route('user.foods.mine')->with('success', 'Pengajuan terkirim. Admin akan memeriksanya.');
    }

    public function mine(Request $request): Response
    {
        $rows = Makanan::query()->where('pengaju_id', $request->user()->id)->latest('id')->limit(100)->get()
            ->map(fn (Makanan $m) => self::card($m) + [
                'diajukan' => $m->created_at?->toDateString(), 'alasan_penolakan' => $m->alasan_penolakan,
            ]);

        return Inertia::render('User/Makanan/Saya', ['submissions' => $rows]);
    }

    /** @return array<string, mixed> */
    private static function card(Makanan $m): array
    {
        $quantity = rtrim(rtrim(number_format((float) $m->kuantitas, 2, '.', ''), '0'), '.');

        return [
            'id' => $m->id, 'nama' => $m->nama, 'status' => $m->status, 'energi' => (float) $m->energi,
            'protein' => (float) $m->protein, 'takaran_saji' => $quantity.' '.$m->satuan,
            'foto_makanan_url' => $m->foto_makanan_url, 'kategori_makanan_id' => $m->kategori_makanan_id,
        ];
    }
}
