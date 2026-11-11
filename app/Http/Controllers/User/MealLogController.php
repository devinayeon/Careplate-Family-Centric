<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\MealLogRequest;
use App\Models\Makanan;
use App\Models\PelacakanMakanan;
use App\Services\MealLog\MealDayView;
use App\Services\MealLog\MealLogService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Catatan Makanan" (Figma 03): a user's meal log. Thin HTTP layer; writes go through MealLogService, the page data
 * through MealDayView. Another user's log is always 404.
 */
class MealLogController extends Controller
{
    public function __construct(private readonly MealLogService $logs) {}

    public function index(Request $request, MealDayView $view): Response
    {
        $today = CarbonImmutable::today();
        $day = $this->day($request->query('tanggal'), $today);
        $tambah = $request->query('tambah');
        $food = is_numeric($tambah) ? Makanan::query()->where('status', 'terverifikasi')->find((int) $tambah) : null;

        return Inertia::render('User/CatatanMakanan', $view->build($this->logs->currentPengguna($request->user()), $day) + [
            'hariIni' => $today->toDateString(),
            'tambah' => $food === null ? null : MealDayView::foodOption($food),
        ]);
    }

    /** JSON for the food picker of the add/edit sheet: verified foods only, at most 10. */
    public function foods(Request $request): JsonResponse
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $foods = Makanan::query()->where('status', 'terverifikasi')
            ->when($q !== '', fn ($query) => $query->where('nama', 'like', '%'.addcslashes($q, '%_\\').'%'))
            ->orderBy('nama')->limit(10)->get()
            ->map(fn (Makanan $m) => MealDayView::foodOption($m))->all();

        return response()->json(['data' => $foods]);
    }

    public function store(MealLogRequest $request): RedirectResponse
    {
        /** @var array{makanan_id: int|string, waktu_makan: string, jumlah_porsi: int|float|string, tanggal_konsumsi: string} $data */
        $data = $request->validated();
        $log = $this->logs->record($request->user(), $data);

        return redirect()->route('user.meals', ['tanggal' => $log->tanggal_konsumsi->toDateString()])
            ->with('success', 'Catatan tersimpan.');
    }

    public function update(MealLogRequest $request, PelacakanMakanan $pelacakan): RedirectResponse
    {
        abort_unless($this->logs->owns($request->user(), $pelacakan), 404);
        /** @var array{makanan_id: int|string, waktu_makan: string, jumlah_porsi: int|float|string} $data */
        $data = $request->validated();
        $this->logs->update($pelacakan, $data);

        return redirect()->route('user.meals', ['tanggal' => $pelacakan->tanggal_konsumsi->toDateString()])
            ->with('success', 'Catatan diperbarui.');
    }

    public function destroy(Request $request, PelacakanMakanan $pelacakan): RedirectResponse
    {
        abort_unless($this->logs->owns($request->user(), $pelacakan), 404);
        $tanggal = $pelacakan->tanggal_konsumsi->toDateString();
        $pelacakan->delete();

        return redirect()->route('user.meals', ['tanggal' => $tanggal])->with('success', 'Catatan dihapus.');
    }

    /** A valid past or current Y-m-d, otherwise today (no logging into the future). */
    private function day(mixed $value, CarbonImmutable $today): CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return $today;
        }
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $day === null || $day->toDateString() !== $value || $day->gt($today) ? $today : $day;
    }
}
