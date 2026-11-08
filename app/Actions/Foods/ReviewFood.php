<?php

namespace App\Actions\Foods;

use App\Exceptions\Ai\AiException;
use App\Models\Food;
use App\Models\FoodRevision;
use App\Models\FoodSubmission;
use App\Models\User;
use App\Services\Ai\Foods\SnapshotManifest;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of foods.status / current_revision / search_text. Each call is ONE DB::transaction
 * (sqlite IMMEDIATE), no provider call inside: either every row of the decision commits or none.
 */
class ReviewFood
{
    private ReviewCandidate $candidates;

    public function __construct(?SnapshotManifest $manifest = null)
    {
        $this->candidates = new ReviewCandidate($manifest ?? new SnapshotManifest(base_path('ai/data/snapshot.manifest.json')));
    }

    /**
     * expectedRevision is the submission's revision_counter. Identical replays return the stored result.
     *
     * @return array{food_id: string, revision: int, status: string, sync_status: string}
     */
    public function approve(FoodSubmission|string $id, ?User $actor, string $auditId, int $expectedRevision, string $note, bool $acknowledgeFlags = false): array
    {
        $submissionId = $id instanceof FoodSubmission ? (string) $id->getKey() : $id;
        $note = $this->required($note);
        $hash = ReviewSnapshot::hash('approve', $submissionId, $actor, $auditId, $expectedRevision, $note, $acknowledgeFlags);

        return DB::transaction(function () use ($submissionId, $actor, $auditId, $expectedRevision, $note, $acknowledgeFlags, $hash) {
            $submission = FoodSubmission::query()->find($submissionId) ?? throw AiException::for('NOT_FOUND');
            /** @var array{food_id: string, revision: int, status: string, sync_status: string}|null $stored */
            $stored = $submission->decision_result;
            if ($submission->status === 'approved' && $submission->decision_hash === $hash && is_array($stored)) {
                return $stored;
            }
            if ($submission->status !== 'pending' || $submission->revision_counter !== $expectedRevision) {
                throw AiException::for('STALE_REVISION');
            }
            $facts = $this->candidates->validate($submission, $acknowledgeFlags);

            $food = Food::query()->where([
                'source_provider' => $submission->source_provider, 'source_id' => $submission->source_id,
                'snapshot_version' => $submission->snapshot_version,
            ])->first() ?? new Food;
            $revision = $food->exists ? $food->current_revision + 1 : 1;
            $food->fill($facts['nutrients'] + [
                'name' => $facts['name'], 'aliases' => $facts['aliases'], 'basis' => '100g',
                'energy_method' => $facts['energy_method'], 'source_description' => $facts['source_description'],
                'source_provider' => $submission->source_provider, 'source_id' => $submission->source_id,
                'source_url' => $submission->source_url, 'snapshot_version' => $submission->snapshot_version,
                'source_sha256' => $submission->source_sha256, 'status' => 'verified', 'current_revision' => $revision,
                'search_text' => FoodSearchText::build($facts['name'], $facts['aliases'], $facts['source_description']),
                'verifier_audit_id' => $auditId, 'verified_at' => now(), 'verified_by' => $actor?->getKey(),
            ])->save();

            $result = $this->result($food);
            $this->appendRevision($food, 'approve', $actor, $auditId, $note, $hash, $result);
            $submission->forceFill([
                'status' => 'approved', 'food_id' => $food->id, 'decision_hash' => $hash, 'decision_result' => $result,
                'decided_by' => $actor?->getKey(), 'decided_at' => now(), 'revision_counter' => $submission->revision_counter + 1,
            ])->save();

            return $result;
        });
    }

    /**
     * expectedRevision is foods.current_revision. Identical replays return the stored result.
     *
     * @return array{food_id: string, revision: int, status: string, sync_status: string}
     */
    public function revoke(Food|string $id, ?User $actor, string $auditId, int $expectedRevision, string $reason): array
    {
        $foodId = $id instanceof Food ? (string) $id->getKey() : $id;
        $reason = $this->required($reason);
        $hash = ReviewSnapshot::hash('revoke', $foodId, $actor, $auditId, $expectedRevision, $reason, false);

        return DB::transaction(function () use ($foodId, $actor, $auditId, $expectedRevision, $reason, $hash) {
            $food = Food::query()->find($foodId) ?? throw AiException::for('NOT_FOUND');
            $replay = FoodRevision::query()->where(['food_id' => $food->id, 'action' => 'revoke', 'decision_hash' => $hash])->first();
            /** @var array{food_id: string, revision: int, status: string, sync_status: string}|null $stored */
            $stored = $replay?->result;
            if (is_array($stored)) {
                return $stored;
            }
            if ($food->status !== 'verified' || $food->current_revision !== $expectedRevision) {
                throw AiException::for('STALE_REVISION');
            }

            $food->forceFill(['status' => 'revoked', 'current_revision' => $expectedRevision + 1, 'search_text' => null])->save();
            $result = $this->result($food);
            $this->appendRevision($food, 'revoke', $actor, $auditId, $reason, $hash, $result);

            return $result;
        });
    }

    private function required(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            throw AiException::for('INVALID_INPUT', 'Data yang dikirim tidak valid (NOTE_REQUIRED).');
        }

        return $text;
    }

    /** @return array{food_id: string, revision: int, status: string, sync_status: string} */
    private function result(Food $food): array
    {
        return ['food_id' => $food->id, 'revision' => $food->current_revision, 'status' => $food->status, 'sync_status' => 'ready'];
    }

    /** @param  array<string, mixed>  $result */
    private function appendRevision(Food $food, string $action, ?User $actor, string $auditId, string $note, string $hash, array $result): void
    {
        FoodRevision::create([
            'food_id' => $food->id, 'revision' => $food->current_revision, 'action' => $action, 'status' => $food->status,
            'snapshot' => ReviewSnapshot::of($food), 'actor_id' => $actor?->getKey(), 'actor_audit_id' => $auditId,
            'note' => $note, 'decision_hash' => $hash, 'result' => $result,
        ]);
    }
}
