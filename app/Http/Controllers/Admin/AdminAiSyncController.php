<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Food;
use App\Models\Makanan;
use App\Services\Ai\Foods\MakananSource;
use App\Services\Ai\Foods\SyncMakananToFoods;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * "Coba lagi" on the dashboard panel "Sinkronisasi Chat Gizi": publishes the verified foods the AI chat is missing
 * (the same single path as ai:sync-makanan, through ReviewFood). Rows that only need a weight are not touched.
 */
class AdminAiSyncController extends Controller
{
    public function run(SyncMakananToFoods $sync): RedirectResponse
    {
        $published = Food::verified()->where('source_provider', MakananSource::PROVIDER)->pluck('source_id')->flip();
        $done = 0;
        $failed = 0;

        Makanan::query()->where('status', MakananSource::VERIFIED_STATUS)->orderBy('id')->each(function (Makanan $m) use ($sync, $published, &$done, &$failed): void {
            if (MakananSource::ineligibleReason($m) !== null || $published->has((string) $m->id)) {
                return;
            }
            try {
                $sync->sync($m);
                $done++;
            } catch (Throwable) {
                $failed++;
            }
        });

        return back()->with($failed === 0 ? 'success' : 'error', $failed === 0
            ? ($done === 0 ? 'Tidak ada yang perlu disinkronkan.' : "{$done} makanan sekarang dipakai Chat Gizi.")
            : "{$done} berhasil, {$failed} gagal. Coba lagi nanti atau lihat log ai.makanan_sync_failed.");
    }
}
