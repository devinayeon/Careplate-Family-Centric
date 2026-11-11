<?php

namespace App\Observers;

use App\Models\Makanan;
use App\Services\Ai\Foods\SyncMakananToFoods;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps the AI food catalog in step with the team's `makanan` master table. Runs after the team's own
 * transaction commits and never lets an AI-side failure break the admin screen: a failed sync is logged
 * (metadata only) and `php artisan ai:sync-makanan` repairs it later.
 */
class MakananObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly SyncMakananToFoods $sync) {}

    public function saved(Makanan $makanan): void
    {
        $this->run($makanan);
    }

    public function deleted(Makanan $makanan): void
    {
        $this->run($makanan);
    }

    public function restored(Makanan $makanan): void
    {
        $this->run($makanan);
    }

    private function run(Makanan $makanan): void
    {
        if (! config('ai.sync_makanan')) {
            return;
        }

        try {
            $this->sync->sync($makanan);
        } catch (Throwable $e) {
            Log::warning('ai.makanan_sync_failed', ['makanan_id' => $makanan->getKey(), 'error' => $e::class]);
        }
    }
}
