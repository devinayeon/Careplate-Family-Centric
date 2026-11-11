<?php

namespace App\Console\Commands\Ai;

use App\Actions\Foods\ApprovePendingCatalog;
use Illuminate\Console\Command;

/** Approves all still-pending USDA submissions through ReviewFood (script audit id, no human review). */
class SeedCatalog extends Command
{
    protected $signature = 'ai:seed-catalog {--allow-script-approval : Required in production: approvals are by script, not by a human reviewer}';

    protected $description = 'Approve pending USDA food submissions through ReviewFood (manifest source id and sha256 match only; values not re-verified; no human review).';

    public function handle(ApprovePendingCatalog $catalog): int
    {
        if (app()->environment('production') && ! $this->option('allow-script-approval')) {
            $this->error('Refused: in production approve foods with ai:review-food (human reviewer). Use --allow-script-approval only if script approval is acceptable.');

            return self::FAILURE;
        }

        $report = $catalog->run();
        foreach ($report['approved'] as $row) {
            $flags = $row['review_reasons'] === [] ? '' : ' ['.implode(', ', $row['review_reasons']).']';
            $this->line(sprintf('%s %s revision %d%s', $row['status'], (string) $row['name'], $row['revision'], $flags));
        }
        foreach ($report['failed'] as $row) {
            $this->error("{$row['submission_id']}: {$row['reason']}");
        }
        $this->line(sprintf('approved %d, failed %d', count($report['approved']), count($report['failed'])));

        return $report['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
