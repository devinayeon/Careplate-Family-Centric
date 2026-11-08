<?php

namespace App\Console\Commands\Ai;

use App\Services\Ai\Chat\TurnGate;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Daily physical cleanup behind the 7-day chat retention (reads already hide expired turns without waiting for it).
 *
 * Boundaries, all strict "older than" so a row exactly on the line is kept (TurnGate/ChatHistory read
 * created_at >= cutoff as visible):
 *  1. chat_turns.created_at < now - ai.chat.retention_days: deleted whatever the status, expired pending included.
 *     A slot still pointing at a deleted turn is released (only by that turn id, never a taker-over's slot).
 *  2. ACTIVE sessions with no turn left and updated_at < the same cutoff are deleted.
 *  3. DELETED tombstones (request_hash and ids survive SessionEraser) are deleted with their turns once
 *     updated_at (the deletion time) < now - request horizon, where
 *     horizon = max(ai.chat.lease_seconds, ai.chat.deadline_seconds) + SAFETY_MARGIN_SECONDS. This is the only
 *     rule that acts on rows younger than the retention cutoff: a late provider result fails its generation CAS
 *     inside that window, so the tombstone is not needed afterwards.
 * A session holding a pending turn with a VALID lease is never touched. Each chunk (by id) is one short
 * transaction; a failure leaves earlier chunks done and the rest consistent. Output is counts only.
 *
 * --dry-run executes the real purge inside one outer transaction that is always rolled back, so counts are exact.
 * On SQLite that transaction holds the write lock (IMMEDIATE mode) for the whole run: run it off-peak.
 */
class PurgeChats extends Command
{
    /** Added to the provider horizon before a tombstone may go. */
    public const SAFETY_MARGIN_SECONDS = 86400;

    /** Ids per transaction. Static only so tests can force several chunks; the default is the production value. */
    public static int $chunkSize = 200;

    protected $signature = 'ai:purge-chats {--dry-run : Report the counts a real run would delete, delete nothing}';

    protected $description = 'Delete chat turns past the retention window, emptied old sessions and expired deletion tombstones (counts only).';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        try {
            $counts = $dry ? $this->simulate(Carbon::now()) : $this->purge(Carbon::now());
        } catch (Throwable $e) {
            report($e);
            $this->error('Chat purge failed ('.$e::class.'); completed chunks stay committed, nothing is half-deleted.');

            return self::FAILURE;
        }

        $this->line(sprintf('%sturns %d, sessions %d', $dry ? 'dry-run: would delete ' : 'deleted ', $counts['turns'], $counts['sessions']));

        return self::SUCCESS;
    }

    /**
     * Runs the real purge inside one outer transaction that is always rolled back.
     *
     * @return array{turns: int, sessions: int}
     */
    private function simulate(Carbon $now): array
    {
        DB::beginTransaction();
        try {
            return $this->purge($now);
        } finally {
            DB::rollBack();
        }
    }

    /** @return array{turns: int, sessions: int} */
    private function purge(Carbon $now): array
    {
        // One definition of "expired" shared with the read side (ChatHistory, MemoryBuilder, TurnGate replay).
        $cutoff = TurnGate::retentionCutoff();
        $horizon = $now->copy()->subSeconds($this->horizonSeconds());
        $turns = $sessions = 0;

        $tombstones = fn (): Builder => $this->unprotected(DB::table('chat_sessions'), 'id', $now)
            ->where('state', 'deleted')->where('updated_at', '<', $horizon);
        $this->chunks($tombstones, function (array $ids) use (&$turns, &$sessions, $tombstones) {
            $turns += DB::table('chat_turns')->whereIn('session_id', $ids)->delete();
            $sessions += $tombstones()->whereIn('id', $ids)->delete();
        });

        $expired = fn (): Builder => $this->unprotected(DB::table('chat_turns'), 'session_id', $now)
            ->where('created_at', '<', $cutoff);
        $this->chunks($expired, function (array $ids) use (&$turns, $expired) {
            $turns += $expired()->whereIn('id', $ids)->delete();
            DB::table('chat_sessions')->whereIn('active_turn_id', $ids)->update([
                'active_turn_id' => null, 'active_token' => null, 'active_lease_expires_at' => null,
            ]);
        });

        $emptied = fn (): Builder => $this->unprotected(DB::table('chat_sessions'), 'id', $now)
            ->where('state', 'active')->where('updated_at', '<', $cutoff)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('chat_turns')->whereColumn('chat_turns.session_id', 'chat_sessions.id'));
        $this->chunks($emptied, function (array $ids) use (&$sessions, $emptied) {
            $sessions += $emptied()->whereIn('id', $ids)->delete();
        });

        return ['turns' => $turns, 'sessions' => $sessions];
    }

    /**
     * Select one id chunk and hand it to $work, both inside ONE transaction, until the scope yields nothing.
     * The scope must stop matching what $work removes, which keeps the loop finite.
     *
     * @param  Closure(): Builder  $scope
     * @param  Closure(list<string>): void  $work
     */
    private function chunks(Closure $scope, Closure $work): void
    {
        do {
            $done = DB::transaction(function () use ($scope, $work): bool {
                $ids = array_values($scope()->orderBy('id')->limit(max(1, self::$chunkSize))->pluck('id')->all());
                if ($ids !== []) {
                    $work($ids);
                }

                return $ids === [];
            });
        } while (! $done);
    }

    /** Excludes sessions (by $column) that currently hold a pending turn with a valid lease. */
    private function unprotected(Builder $query, string $column, Carbon $now): Builder
    {
        return $query->whereNotIn($column, fn ($sub) => $sub->select('session_id')->from('chat_turns')
            ->where('status', 'pending')->where('lease_expires_at', '>', $now));
    }

    private function horizonSeconds(): int
    {
        return max((int) config('ai.chat.lease_seconds'), (int) config('ai.chat.deadline_seconds')) + self::SAFETY_MARGIN_SECONDS;
    }
}
