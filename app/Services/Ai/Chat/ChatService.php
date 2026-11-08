<?php

namespace App\Services\Ai\Chat;

use App\Exceptions\Ai\AiException;
use App\Models\ChatSession;
use App\Models\ChatTurn;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One chat turn end to end (spec 04 section C):
 * owner check -> HMAC request hash -> TurnGate::reserve -> replay of a terminal key (no work) ->
 * ChatComposer (retrieval, templates, at most one model call, all OUTSIDE any transaction) -> TurnGate::finalize.
 *
 * Every expected failure is an AiException. Whatever fails after reserve closes the turn through TurnGate::fail
 * with that code, so the key replays the same terminal error and the session slot is freed. A result that arrives
 * after the session was deleted or the lease lost is never written.
 */
class ChatService
{
    public function __construct(
        private readonly TurnGate $gate,
        private readonly ChatComposer $composer,
    ) {}

    /**
     * @param  array{message: string, client_turn_id: string, food_id: string|null, amount_g: string|null}  $input  SendTurnRequest::turn()
     * @return array<string, mixed> ChatResponse (contracts/chat.schema.json)
     *
     * @throws AiException
     */
    public function send(User $user, ChatSession $session, array $input, string $requestId): array
    {
        $start = hrtime(true);
        // reserve() does not check ownership: the route policy does, and this repeats it for any other caller.
        if ((string) $session->user_id !== (string) $user->getKey() || $session->state !== 'active') {
            throw AiException::for('NOT_FOUND');
        }

        $reserved = $this->gate->reserve($session, $input['client_turn_id'], self::requestHash($input), [
            'message' => $input['message'], 'food_id' => $input['food_id'], 'amount_g' => $input['amount_g'],
        ]);
        if ($reserved->isReplay()) {
            return $this->replay($reserved, $requestId, $start);
        }

        $turn = $reserved->turn;
        $fallback = null;
        try {
            $answer = $this->composer->compose($session, $input);
            $fallback = $answer->fallback;
            $response = self::response($session, $turn, $input, $requestId, $answer->part);
            if (! $this->gate->finalize($turn, $response, $answer->part['citations'], $answer->foodRefs)) {
                throw $this->lateResult($turn);
            }
            // Commit, then answer with the STORED result (spec 04): the first response and every replay are the same
            // value on every database, whatever order the JSON column hands object keys back in. A delete committed
            // in between has already erased it: that is a late result like any other (404), never rebuilt.
            $response = ChatTurn::query()->find($turn->id)->response ?? throw $this->lateResult($turn);
        } catch (AiException $e) {
            $this->abort($turn, $e->errorCode);
            $this->log($requestId, $e->errorCode, null, $start);

            throw $e;
        } catch (Throwable) {
            // The original may quote SQL with the message text: drop it, keep only the code.
            $this->abort($turn, 'INTERNAL');
            $this->log($requestId, 'INTERNAL', null, $start);

            throw AiException::for('INTERNAL');
        }

        $this->log($requestId, 'ok', $response['mode'], $start, $fallback);

        return $response;
    }

    /**
     * Keyed hash of {trimmed message, food_id, canonical amount_g}. HMAC with the app key, so the stored
     * value (which outlives session deletion) cannot be used to confirm a guessed message.
     *
     * @param  array{message: string, food_id: string|null, amount_g: string|null}  $input
     */
    public static function requestHash(array $input): string
    {
        $canonical = json_encode(
            ['message' => $input['message'], 'food_id' => $input['food_id'], 'amount_g' => $input['amount_g']],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        return hash_hmac('sha256', $canonical, (string) config('app.key'));
    }

    /** @return array<string, mixed> */
    private function replay(ReserveResult $reserved, string $requestId, int $start): array
    {
        $error = $reserved->error();
        if ($error !== null) {
            $this->log($requestId, $error->errorCode, null, $start, null, true);

            throw $error;
        }
        $stored = $reserved->response();
        if ($stored === null) {
            throw AiException::for('INTERNAL');
        }
        $this->log($requestId, 'ok', is_string($stored['mode'] ?? null) ? $stored['mode'] : null, $start, null, true);

        return $stored;
    }

    /**
     * @param  array{message: string, client_turn_id: string, food_id: string|null, amount_g: string|null}  $input
     * @param  array<string, mixed>  $part
     * @return array<string, mixed>
     */
    private static function response(ChatSession $session, ChatTurn $turn, array $input, string $requestId, array $part): array
    {
        return [
            'schema_version' => '1.0',
            'request_id' => $requestId,
            'session_id' => (string) $session->getKey(),
            'turn_id' => (string) $turn->getKey(),
            'client_turn_id' => $input['client_turn_id'],
            'sequence' => (int) $turn->sequence,
        ] + $part;
    }

    /** finalize() wrote nothing: the session is gone (404, never resurrect it) or the lease/slot was lost. */
    private function lateResult(ChatTurn $turn): AiException
    {
        $live = ChatSession::query()->find($turn->session_id);
        $gone = $live === null || $live->state !== 'active' || (int) $live->generation !== (int) $turn->generation;

        return AiException::for($gone ? 'NOT_FOUND' : 'AI_TIMEOUT');
    }

    /** Closes a still-owned pending turn with the error; a no-op when it is terminal, taken over or deleted. */
    private function abort(ChatTurn $turn, string $code): void
    {
        try {
            $this->gate->fail($turn, $code);
        } catch (Throwable) {
            // The lease expiry closes the turn at the next reserve; never mask the original error.
        }
    }

    /** Allowlisted metadata only: no message, answer, food name or provider text. */
    private function log(string $requestId, string $status, ?string $mode, int $start, ?string $fallback = null, bool $replay = false): void
    {
        Log::info('ai.chat', array_filter([
            'request_id' => $requestId,
            'feature' => 'chat',
            'status' => $status,
            'mode' => $mode,
            'fallback' => $fallback,
            'replay' => $replay ?: null,
            'latency_ms' => (int) round((hrtime(true) - $start) / 1_000_000),
        ], fn ($v) => $v !== null));
    }
}
