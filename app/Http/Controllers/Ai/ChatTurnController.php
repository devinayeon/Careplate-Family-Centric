<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Services\Ai\Chat\ChatHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET history of one owned session, ordered by sequence. Pending and failed turns are listed with their
 * state; turns past the retention window are omitted. Sending a turn (POST) is added by Task 11.
 */
class ChatTurnController extends Controller
{
    public function index(Request $request, ChatSession $session, ChatHistory $history): JsonResponse
    {
        $input = ChatSessionController::page($request);

        return response()->json($history->turns($request->user(), $session, $input['cursor'], $input['limit']));
    }
}
