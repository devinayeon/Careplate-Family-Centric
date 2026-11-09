<?php

namespace Database\Seeders;

use App\Actions\Foods\ApprovePendingCatalog;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Same flow as `php artisan ai:seed-catalog`. Run it explicitly; it is not part of DatabaseSeeder.
 */
class AiMvpFoodSeeder extends Seeder
{
    public function run(): void
    {
        $report = app(ApprovePendingCatalog::class)->run();
        if ($report['failed'] !== []) {
            throw new RuntimeException('Catalog seeding refused '.count($report['failed']).' submission(s): '.$report['failed'][0]['reason']);
        }
    }
}
