<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Ai\AiResponseHeaders;
use App\Http\Requests\Ai\SendTurnRequest;
use App\Models\ChatSession;
use App\Services\Ai\Chat\ChatHistory;
use App\Services\Ai\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET history of one owned session, ordered by sequence. Pending and failed turns are listed with their
 * state; turns past the retention window are omitted. POST sends one turn through ChatService and answers 200,
 * including the stored result of a replayed key.
 */
class ChatTurnController extends Controller
{
    public function index(Request $request, ChatSession $session, ChatHistory $history): JsonResponse
    {
        $input = ChatSessionController::page($request);

        return response()->json($history->turns($request->user(), $session, $input['cursor'], $input['limit']));
    }

    public function store(SendTurnRequest $request, ChatSession $session, ChatService $chat): JsonResponse
    {
        return response()->json($chat->send(
            $request->user(), $session, $request->turn(), AiResponseHeaders::requestId($request),
        ));
    }
}
