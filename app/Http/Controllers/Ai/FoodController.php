<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\Food;
use App\Services\Ai\Foods\FoodSearch;
use App\Services\Ai\Foods\FoodSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /ai/v1/foods: verified catalog only, no model call, no nutrient values (those come from chat).
 * Validation failures render as INVALID_INPUT through AiErrorRenderer.
 */
class FoodController extends Controller
{
    public function __invoke(Request $request, FoodSearch $search): JsonResponse
    {
        $input = $request->validate([
            'q' => ['nullable', 'string', 'max:'.FoodSearch::MAX_QUERY],
            'limit' => ['nullable', 'integer', 'between:1,'.FoodSearch::MAX_CANDIDATES],
        ]);

        $foods = $search->list((string) ($input['q'] ?? ''), (int) ($input['limit'] ?? FoodSearch::MAX_CANDIDATES));

        return response()->json([
            'data' => $foods->map(fn (Food $food) => FoodSource::summary($food))->values()->all(),
        ]);
    }
}
