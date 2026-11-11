<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Foods\ReviewFood;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\ReviewDecisionRequest;
use Illuminate\Http\JsonResponse;

/**
 * Thin HTTP wrapper over ReviewFood (the only publish path). Access (permission food:verify AND role admin)
 * is enforced by route middleware before this runs; ids are plain strings so ReviewFood answers 404/409/422.
 */
class FoodReviewController extends Controller
{
    public function approve(ReviewDecisionRequest $request, ReviewFood $review, string $submission): JsonResponse
    {
        $d = $request->decision();

        return response()->json($review->approve(
            $submission, $request->user(), self::auditId($request), $d['expected_revision'], $d['text'], $d['acknowledge'],
        ));
    }

    public function revoke(ReviewDecisionRequest $request, ReviewFood $review, string $food): JsonResponse
    {
        $d = $request->decision();

        return response()->json($review->revoke(
            $food, $request->user(), self::auditId($request), $d['expected_revision'], $d['text'],
        ));
    }

    /** Pseudonymous reviewer id for the audit trail: keyed hash of the user id, never an email or name. */
    private static function auditId(ReviewDecisionRequest $request): string
    {
        return 'user:'.substr(hash_hmac('sha256', (string) $request->user()->getKey(), (string) config('app.key')), 0, 16);
    }
}
