<?php

namespace App\Console\Commands\Ai;

use App\Actions\Foods\ReviewFood;
use App\Exceptions\Ai\AiException;
use App\Models\Food;
use App\Models\FoodSubmission;
use App\Models\User;
use Illuminate\Console\Command;

/** CLI front for ReviewFood: `ai:review-food approve --submission=ID ...` or `revoke --food=ID ...`. */
class ReviewFoodCommand extends Command
{
    protected $signature = 'ai:review-food {action : approve or revoke}
        {--submission= : Submission id (approve)}
        {--food= : Food id (revoke)}
        {--reviewer-email= : Reviewer user email (optional; omitted = script actor)}
        {--audit-id= : Audit id (default cli:ai-review-food)}
        {--note= : Reviewer note (approve)}
        {--reason= : Reason (revoke)}
        {--expected-revision= : Expected revision (default: current)}
        {--acknowledge-flags : Acknowledge candidate review flags (approve)}';

    protected $description = 'Approve a pending food submission or revoke a verified food through ReviewFood.';

    public function handle(ReviewFood $review): int
    {
        $action = (string) $this->argument('action');
        if (! in_array($action, ['approve', 'revoke'], true)) {
            $this->error('Action must be approve or revoke.');

            return self::INVALID;
        }
        $actor = null;
        if ($this->option('reviewer-email')) {
            $actor = User::query()->where('email', (string) $this->option('reviewer-email'))->first();
            if ($actor === null) {
                $this->error('Reviewer not found.');

                return self::FAILURE;
            }
        }
        $audit = (string) ($this->option('audit-id') ?: 'cli:ai-review-food');

        try {
            $result = $action === 'approve' ? $this->approve($review, $actor, $audit) : $this->revoke($review, $actor, $audit);
        } catch (AiException $e) {
            $this->error("{$e->errorCode}: {$e->getMessage()}");

            return self::FAILURE;
        }
        $this->line(sprintf('%s food %s revision %d (sync %s)', $result['status'], $result['food_id'], $result['revision'], $result['sync_status']));

        return self::SUCCESS;
    }

    /** @return array{food_id: string, revision: int, status: string, sync_status: string} */
    private function approve(ReviewFood $review, ?User $actor, string $audit): array
    {
        $submission = FoodSubmission::query()->find((string) $this->option('submission')) ?? throw AiException::for('NOT_FOUND');
        $expected = $this->option('expected-revision') !== null ? (int) $this->option('expected-revision') : $submission->revision_counter;

        return $review->approve($submission, $actor, $audit, $expected, (string) $this->option('note'), (bool) $this->option('acknowledge-flags'));
    }

    /** @return array{food_id: string, revision: int, status: string, sync_status: string} */
    private function revoke(ReviewFood $review, ?User $actor, string $audit): array
    {
        $food = Food::query()->find((string) $this->option('food')) ?? throw AiException::for('NOT_FOUND');
        $expected = $this->option('expected-revision') !== null ? (int) $this->option('expected-revision') : $food->current_revision;

        return $review->revoke($food, $actor, $audit, $expected, (string) $this->option('reason'));
    }
}
