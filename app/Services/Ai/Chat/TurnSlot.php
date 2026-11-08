<?php

namespace App\Services\Ai\Chat;

use App\Exceptions\Ai\AiException;
use App\Models\ChatSession;
use App\Models\ChatTurn;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Conditional-update (CAS) primitives behind TurnGate. Every write states the exact row version it expects
 * (turn id+token+status, session id+active slot+generation) and reports affected rows; nothing reads-then-writes
 * blindly. Callers run these inside one short DB::transaction (SQLite IMMEDIATE), never around a provider call.
 */
class TurnSlot
{
    /**
     * One short write transaction. Lock contention or a racing insert becomes a controlled retryable
     * SESSION_BUSY, never a 500 and never an automatic model retry.
     *
     * @template T
     *
     * @param  Closure(): T  $fn
     * @return T
     */
    public function atomic(Closure $fn): mixed
    {
        try {
            return DB::transaction($fn);
        } catch (UniqueConstraintViolationException $e) {
            throw AiException::for('SESSION_BUSY', previous: $e);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'database is locked') || str_contains($e->getMessage(), 'database table is locked')) {
                throw AiException::for('SESSION_BUSY', previous: $e);
            }
            throw $e;
        }
    }

    /** Fresh row read inside the caller's transaction; never trusts a model the caller already holds. */
    public function session(string $sessionId): ?ChatSession
    {
        return ChatSession::query()->find($sessionId);
    }

    public function turn(string $turnId): ?ChatTurn
    {
        return ChatTurn::query()->find($turnId);
    }

    public static function leaseValid(?Carbon $leaseExpiresAt, Carbon $now): bool
    {
        return $leaseExpiresAt !== null && $leaseExpiresAt->gt($now);
    }

    /** pending -> failed, only for the exact turn version (id + token + still pending). */
    public function close(string $turnId, string $token, string $errorCode, Carbon $now): bool
    {
        return DB::table('chat_turns')
            ->where('id', $turnId)->where('token', $token)->where('status', TurnGate::PENDING)
            ->update(['status' => TurnGate::FAILED, 'error_code' => $errorCode, 'updated_at' => $now]) === 1;
    }

    /**
     * Empties the session slot only while it still belongs to this turn at this generation, so cleanup can
     * never clear a slot another turn has already taken over.
     *
     * @param  array<string, mixed>  $extra
     */
    public function release(string $sessionId, string $turnId, string $token, int $generation, Carbon $now, array $extra = []): bool
    {
        return DB::table('chat_sessions')
            ->where('id', $sessionId)->where('generation', $generation)
            ->where('active_turn_id', $turnId)->where('active_token', $token)
            ->update(['active_turn_id' => null, 'active_token' => null, 'active_lease_expires_at' => null, 'updated_at' => $now] + $extra) === 1;
    }

    /**
     * True when any cited food (citations and food refs) is no longer verified at the cited revision.
     * Malformed entries count as stale: never complete on facts that cannot be re-checked.
     *
     * @param  array<mixed>  $citations
     * @param  array<mixed>  $foodRefs
     */
    public function staleKnowledge(array $citations, array $foodRefs): bool
    {
        $cited = [];
        foreach ([...$citations, ...$foodRefs] as $entry) {
            $id = is_array($entry) ? ($entry['food_id'] ?? null) : null;
            $revision = is_array($entry) ? ($entry['revision'] ?? null) : null;
            if (! is_string($id) || ! is_int($revision)) {
                return true;
            }
            $cited[$id][$revision] = true;
        }
        if ($cited === []) {
            return false;
        }

        $live = DB::table('foods')->whereIn('id', array_keys($cited))->where('status', 'verified')
            ->pluck('current_revision', 'id');
        foreach ($cited as $id => $revisions) {
            if (! $live->has($id) || array_keys($revisions) !== [(int) $live[$id]]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Follow-ups may carry a food only when exactly one food was identified.
     *
     * @param  array<mixed>  $foodRefs
     */
    public static function soleFood(array $foodRefs): ?string
    {
        $ids = array_values(array_unique(array_filter(array_map(
            fn ($ref) => is_array($ref) && is_string($ref['food_id'] ?? null) ? $ref['food_id'] : null,
            $foodRefs,
        ))));

        return count($ids) === 1 ? $ids[0] : null;
    }
}
