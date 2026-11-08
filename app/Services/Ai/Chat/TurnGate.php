<?php

namespace App\Services\Ai\Chat;

use App\Exceptions\Ai\AiException;
use App\Models\ChatSession;
use App\Models\ChatTurn;
use Illuminate\Support\Carbon;

/**
 * Idempotent turn reservation and finalisation (spec 04 section C, 06 section 4).
 *
 * Protocol: reserve() in one short transaction -> retrieval/provider work OUTSIDE any transaction ->
 * finalize() or fail() in one short transaction. Session slot = active_turn_id + active_token +
 * active_lease_expires_at, fenced by session.generation (bumped on delete). All writes are conditional
 * updates on the exact expected row version; a late or foreign writer changes nothing. Each transaction first
 * locks the session row, then the turn row (TurnSlot, FOR UPDATE; a no-op on SQLite), so on MySQL every check is
 * a current read and two writers on one session queue (or fail as SESSION_BUSY) instead of racing.
 */
class TurnGate
{
    public const PENDING = 'pending';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    public function __construct(private readonly TurnSlot $slot) {}

    /**
     * @param  array{message?: string|null, food_id?: string|null, amount_g?: string|null}  $turnInput  stored on a NEW turn only
     *
     * @throws AiException NOT_FOUND (deleted/missing session), IDEMPOTENCY_CONFLICT, TURN_IN_PROGRESS, SESSION_BUSY
     */
    public function reserve(ChatSession $session, string $clientTurnId, string $requestHash, array $turnInput = []): ReserveResult
    {
        return $this->slot->atomic(function () use ($session, $clientTurnId, $requestHash, $turnInput) {
            $now = now();
            $live = $this->slot->session((string) $session->getKey());
            if ($live === null || $live->state !== 'active') {
                throw AiException::for('NOT_FOUND');
            }

            // A plain read is enough here: every turn write that changes what reserve decides on (TurnGate,
            // SessionEraser) holds the session row lock taken above. PurgeChats deletes turns without it, but only
            // turns past retention, which a replay refuses anyway. FOR UPDATE on a missing (session_id, client_turn_id)
            // key would gap-lock the unique index and let two new sessions' first inserts deadlock each other.
            $existing = ChatTurn::query()->where('session_id', $live->id)->where('client_turn_id', $clientTurnId)->first();
            if ($existing !== null) {
                return $this->replay($live, $existing, $requestHash, $now);
            }

            $this->clearExpiredSlot($live, $now);

            return ReserveResult::fresh($this->open($live, $clientTurnId, $requestHash, $turnInput, $now));
        });
    }

    /**
     * Completes the turn with the server-built response. Returns false and writes NOTHING when the turn is not
     * pending, the token/lease no longer holds, or the session was deleted/vanished/taken over (late result).
     *
     * @param  array<string, mixed>  $response  stored ChatResponse, replayed verbatim
     * @param  list<array<string, mixed>>  $citations  each with food_id + revision (AnswerRenderer shape)
     * @param  list<array{food_id: string, revision: int}>  $foodRefs  foods the turn identified
     *
     * @throws AiException KNOWLEDGE_CHANGED after committing the turn as failed, when a cited food is no
     *                     longer verified at the cited revision
     */
    public function finalize(ChatTurn|string $turn, array $response, array $citations, array $foodRefs, ?string $mode = null): bool
    {
        $outcome = $this->slot->atomic(function () use ($turn, $response, $citations, $foodRefs, $mode) {
            $now = now();
            [$session, $row, $token, $generation] = $this->claim($turn);
            if ($row === null || ! $this->owns($session, $row, $token, $generation, $now)) {
                return false;
            }

            if ($this->slot->staleKnowledge($citations, $foodRefs)) {
                $this->slot->close($row->id, $token, 'KNOWLEDGE_CHANGED', $now);
                $this->slot->release($session->id, $row->id, $token, $generation, $now);

                return 'KNOWLEDGE_CHANGED';
            }

            $released = $this->slot->release($session->id, $row->id, $token, $generation, $now, [
                'last_food_id' => TurnSlot::soleFood($foodRefs),
            ]);
            $completed = ChatTurn::query()
                ->whereKey($row->id)->where('token', $token)->where('status', self::PENDING)
                ->where('generation', $generation)->where('lease_expires_at', '>', $now)
                ->update([
                    'status' => self::COMPLETED,
                    'response' => self::json($response),
                    'citations' => self::json($citations),
                    'food_refs' => self::json($foodRefs),
                    'mode' => $mode ?? (is_string($response['mode'] ?? null) ? $response['mode'] : null),
                    'error_code' => null,
                    'updated_at' => $now,
                ]);
            if (! $released || $completed !== 1) {
                // Unreachable under the session and turn row locks after owns(); roll back rather than write half.
                throw AiException::for('AI_TIMEOUT');
            }

            return true;
        });

        if ($outcome === 'KNOWLEDGE_CHANGED') {
            throw AiException::for('KNOWLEDGE_CHANGED');
        }

        return $outcome;
    }

    /**
     * Marks a still-owned pending turn failed with $errorCode (an AiException catalog code) and releases its
     * slot if still owned. Idempotent: false when already terminal, taken over or the session was deleted.
     */
    public function fail(ChatTurn|string $turn, string $errorCode): bool
    {
        return $this->slot->atomic(function () use ($turn, $errorCode) {
            $now = now();
            [$session, $row, $token, $generation] = $this->claim($turn);
            if ($row === null || $session === null || $session->state !== 'active' || (int) $session->generation !== $generation) {
                return false;
            }
            if (! $this->slot->close($row->id, $token, $errorCode, $now)) {
                return false;
            }
            $this->slot->release($session->id, $row->id, $token, $generation, $now);

            return true;
        });
    }

    public static function retentionCutoff(): Carbon
    {
        return now()->subDays((int) config('ai.chat.retention_days'));
    }

    private function replay(ChatSession $session, ChatTurn $turn, string $requestHash, Carbon $now): ReserveResult
    {
        if (! hash_equals($turn->request_hash, $requestHash)) {
            throw AiException::for('IDEMPOTENCY_CONFLICT');
        }

        if ($turn->status === self::PENDING) {
            // Expired lease is resolved BEFORE TURN_IN_PROGRESS is considered, so a dead worker never pins the key.
            if ($turn->lease_expires_at !== null && $turn->lease_expires_at->gt($now)) {
                throw AiException::for('TURN_IN_PROGRESS');
            }
            if ($this->slot->close($turn->id, $turn->token, 'AI_TIMEOUT', $now)) {
                $this->slot->release($session->id, $turn->id, $turn->token, (int) $turn->generation, $now);
            }
            $turn->refresh();
            if ($turn->status === self::PENDING) {
                throw AiException::for('TURN_IN_PROGRESS');
            }
        }

        // Content past retention is gone even before the purge job: the stored answer is never replayed.
        if ($turn->status === self::COMPLETED && $turn->created_at->lt(self::retentionCutoff())) {
            throw AiException::for('IDEMPOTENCY_CONFLICT');
        }

        return ReserveResult::replay($turn);
    }

    /** A foreign slot with a valid lease is SESSION_BUSY; an expired one is closed AI_TIMEOUT and released. */
    private function clearExpiredSlot(ChatSession $session, Carbon $now): void
    {
        if ($session->active_turn_id === null) {
            return;
        }
        if (TurnSlot::leaseValid($session->active_lease_expires_at, $now)) {
            throw AiException::for('SESSION_BUSY');
        }

        $this->slot->close($session->active_turn_id, (string) $session->active_token, 'AI_TIMEOUT', $now);
        if (! $this->slot->release($session->id, $session->active_turn_id, (string) $session->active_token, (int) $session->generation, $now)) {
            throw AiException::for('SESSION_BUSY');
        }
        $session->active_turn_id = null;
    }

    /** @param array<string, mixed> $turnInput */
    private function open(ChatSession $session, string $clientTurnId, string $requestHash, array $turnInput, Carbon $now): ChatTurn
    {
        $turn = new ChatTurn;
        $turn->id = $turn->newUniqueId();
        $token = bin2hex(random_bytes(32));
        $lease = $now->copy()->addSeconds((int) config('ai.chat.lease_seconds'));
        $sequence = (int) $session->next_sequence;
        $generation = (int) $session->generation;

        $claimed = ChatSession::query()
            ->whereKey($session->id)->where('state', 'active')->where('generation', $generation)
            ->where('next_sequence', $sequence)->whereNull('active_turn_id')
            ->update([
                'next_sequence' => $sequence + 1, 'active_turn_id' => $turn->id, 'active_token' => $token,
                'active_lease_expires_at' => $lease, 'updated_at' => $now,
            ]);
        if ($claimed !== 1) {
            throw AiException::for('SESSION_BUSY');
        }

        $input = array_intersect_key($turnInput, array_flip(['message', 'food_id', 'amount_g']));
        $turn->fill($input + [
            'session_id' => $session->id, 'client_turn_id' => $clientTurnId, 'sequence' => $sequence,
            'request_hash' => $requestHash, 'status' => self::PENDING, 'token' => $token,
            'generation' => $generation, 'lease_expires_at' => $lease,
        ])->save();

        return $turn;
    }

    /**
     * Locks the session row, then the turn row: the one lock order of every chat write (SessionEraser too). The
     * session id never changes, so it comes from the reserved model or an unlocked lookup. Returns the
     * reservation's own token/generation when given the reserved model; the stored ones for an id.
     *
     * @return array{0: ChatSession|null, 1: ChatTurn|null, 2: string, 3: int}
     */
    private function claim(ChatTurn|string $turn): array
    {
        $turnId = $turn instanceof ChatTurn ? (string) $turn->getKey() : $turn;
        $sessionId = $turn instanceof ChatTurn ? $turn->session_id : ChatTurn::query()->whereKey($turnId)->value('session_id');
        $session = is_string($sessionId) ? $this->slot->session($sessionId) : null;
        $row = $session === null ? null : $this->slot->turn($turnId);
        if ($row === null || $row->session_id !== $session->id) {
            return [$session, null, '', 0];
        }

        return $turn instanceof ChatTurn
            ? [$session, $row, (string) $turn->token, (int) $turn->generation]
            : [$session, $row, (string) $row->token, (int) $row->generation];
    }

    private function owns(?ChatSession $session, ChatTurn $row, string $token, int $generation, Carbon $now): bool
    {
        return $session !== null && $session->state === 'active'
            && (int) $session->generation === $generation && (int) $row->generation === $generation
            && $session->active_turn_id === $row->id && hash_equals((string) $session->active_token, $token)
            && $row->status === self::PENDING && hash_equals((string) $row->token, $token)
            && TurnSlot::leaseValid($row->lease_expires_at, $now);
    }

    /** @param array<mixed> $value */
    private static function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
