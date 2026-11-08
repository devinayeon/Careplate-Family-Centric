<?php

namespace App\Actions\Foods;

use App\Exceptions\Ai\AiException;
use App\Models\FoodSubmission;

/**
 * Demo catalog seeding: approves every still-pending USDA submission through ReviewFood. The only
 * script check is that the source id and archive sha256 are listed in the snapshot manifest; nutrient
 * values are NOT re-verified against the raw data. No human reviewer exists, and the audit note says so.
 */
class ApprovePendingCatalog
{
    public const AUDIT_ID = 'script:ai-source-match';

    public const NOTE = 'source id and archive sha256 matched against the snapshot manifest; nutrient values NOT re-verified against the raw data; review flags acknowledged by script; no human nutritionist review';

    public function __construct(private readonly ReviewFood $review) {}

    /** @return array{approved: list<array<string, mixed>>, failed: list<array{submission_id: string, reason: string}>} */
    public function run(): array
    {
        $approved = $failed = [];
        $pending = FoodSubmission::query()->where(['status' => 'pending', 'source_provider' => 'USDA_FDC'])->orderBy('source_id')->get();

        foreach ($pending as $submission) {
            /** @var mixed $raw */
            $raw = $submission->candidate;
            /** @var array<string, mixed> $candidate */
            $candidate = is_array($raw) ? $raw : [];
            $reasons = (array) ($candidate['review_reasons'] ?? []);
            $note = self::NOTE.'. Review flags: '.($reasons === [] ? 'none' : implode(', ', array_map('strval', $reasons))).'.';
            try {
                $approved[] = $this->review->approve($submission, null, self::AUDIT_ID, $submission->revision_counter, $note, true)
                    + ['name' => $candidate['name'] ?? null, 'review_reasons' => $reasons];
            } catch (AiException $e) {
                $failed[] = ['submission_id' => (string) $submission->id, 'reason' => $e->getMessage()];
            }
        }

        return ['approved' => $approved, 'failed' => $failed];
    }
}
