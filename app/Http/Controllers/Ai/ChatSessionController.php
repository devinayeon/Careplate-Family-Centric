<?php

namespace App\Http\Controllers\Ai;

use App\Exceptions\Ai\AiException;
use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Services\Ai\Chat\ChatHistory;
use App\Services\Ai\Chat\HistoryCursor;
use App\Services\Ai\Chat\SessionEraser;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PDOException;

/**
 * Chat sessions of the authenticated owner. The owner always comes from auth; no body field is accepted.
 * Foreign or deleted sessions reach ChatSessionPolicy, which answers 404 through the error envelope.
 */
class ChatSessionController extends Controller
{
    public const MAX_LIMIT = 20;

    public function store(Request $request): JsonResponse
    {
        if ($request->all() !== []) {
            throw AiException::for('INVALID_INPUT');
        }

        try {
            $session = ChatSession::create([
                'user_id' => $request->user()->getKey(), 'title' => null, 'state' => 'active',
                'generation' => 1, 'next_sequence' => 1,
            ])->refresh();
        } catch (QueryException|PDOException $e) {
            // Lock contention (SQLite busy, MySQL lock wait timeout/deadlock) is a retryable DB_BUSY; every other database error propagates unchanged.
            throw AiException::isLockContention($e) ? AiException::for('DB_BUSY', previous: $e) : $e;
        }

        return response()->json(ChatHistory::session($session), 201);
    }

    public function index(Request $request, ChatHistory $history): JsonResponse
    {
        $input = self::page($request);

        return response()->json($history->sessions($request->user(), $input['cursor'], $input['limit']));
    }

    public function destroy(ChatSession $session, SessionEraser $eraser): Response
    {
        $eraser->erase($session);

        return response()->noContent();
    }

    /**
     * @return array{cursor: string|null, limit: int}
     */
    public static function page(Request $request): array
    {
        $input = $request->validate([
            'cursor' => ['nullable', 'string', 'max:'.HistoryCursor::MAX_LENGTH],
            'limit' => ['nullable', 'integer', 'between:1,'.self::MAX_LIMIT],
        ]);

        return [
            'cursor' => isset($input['cursor']) ? (string) $input['cursor'] : null,
            'limit' => (int) ($input['limit'] ?? self::MAX_LIMIT),
        ];
    }
}
