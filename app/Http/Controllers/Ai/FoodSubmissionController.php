<?php

namespace App\Http\Controllers\Ai;

use App\Exceptions\Ai\AiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\SubmitFoodRequest;
use App\Models\FoodSubmission;
use App\Support\Ai\JsonShape;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use PDOException;

/**
 * POST /ai/v1/food-submissions: creates a PENDING submission only. It never touches foods, so the item is not
 * searchable until an admin approves it through ReviewFood. The source URL is stored as metadata, never fetched.
 *
 * Duplicate source triple: the same user repeating an identical, still pending submission gets the existing
 * one back (200, safe retry). Any other duplicate is 409 IDEMPOTENCY_CONFLICT, which never reveals whose it is.
 */
class FoodSubmissionController extends Controller
{
    public function store(SubmitFoodRequest $request): JsonResponse
    {
        $source = $request->source();
        $candidate = $request->candidate();
        $userId = (string) $request->user()->getKey();

        $created = false;
        $submission = $this->existing($source);
        if ($submission === null) {
            try {
                $submission = FoodSubmission::create($source + [
                    'user_id' => $userId, 'status' => 'pending', 'revision_counter' => 1, 'candidate' => $candidate,
                ]);
                $created = true;
            } catch (UniqueConstraintViolationException) {
                // Lost a race with an identical insert: judge the winner below.
                $submission = $this->existing($source) ?? throw AiException::for('IDEMPOTENCY_CONFLICT');
            } catch (QueryException|PDOException $e) {
                // Lock contention (SQLite busy, MySQL lock wait timeout/deadlock) is a retryable DB_BUSY; every other database error propagates unchanged.
                throw AiException::isLockContention($e) ? AiException::for('DB_BUSY', previous: $e) : $e;
            }
        }

        /** @var mixed $stored */
        $stored = $submission->candidate;
        $retry = (string) $submission->user_id === $userId && $submission->status === 'pending' && JsonShape::same($stored, $candidate);
        if (! $created && ! $retry) {
            throw AiException::for('IDEMPOTENCY_CONFLICT', 'Sumber makanan ini sudah pernah diajukan.');
        }

        return response()->json(self::body($submission), $created ? 201 : 200);
    }

    /** @param  array<string, string|null>  $source */
    private function existing(array $source): ?FoodSubmission
    {
        return FoodSubmission::query()->where([
            'source_provider' => $source['source_provider'], 'source_id' => $source['source_id'],
            'snapshot_version' => $source['snapshot_version'],
        ])->first();
    }

    /** @return array{submission_id: string, status: string, revision_counter: int} */
    private static function body(FoodSubmission $submission): array
    {
        return [
            'submission_id' => (string) $submission->getKey(),
            'status' => $submission->status,
            'revision_counter' => $submission->revision_counter,
        ];
    }
}
