<?php

namespace App\Console\Commands\Ai;

use App\Models\Makanan;
use App\Services\Ai\Foods\SyncMakananToFoods;
use Illuminate\Console\Command;
use Throwable;

/** Brings the AI food catalog in line with every row of the team's `makanan` table (backfill and repair). */
class SyncMakanan extends Command
{
    protected $signature = 'ai:sync-makanan';

    protected $description = 'Publish verified makanan rows (gram units) as AI foods through ReviewFood; revoke the ones no longer eligible.';

    public function handle(SyncMakananToFoods $sync): int
    {
        $counts = [];
        $failed = 0;

        Makanan::withTrashed()->orderBy('id')->each(function (Makanan $makanan) use ($sync, &$counts, &$failed): void {
            try {
                $result = $sync->sync($makanan);
            } catch (Throwable $e) {
                $failed++;
                $this->error("makanan {$makanan->getKey()}: ".$e::class);

                return;
            }
            $counts[$result] = ($counts[$result] ?? 0) + 1;
        });

        ksort($counts);
        foreach ($counts as $result => $count) {
            $this->line("{$result}: {$count}");
        }
        $this->line("failed: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
