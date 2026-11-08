<?php

namespace App\Services\Ai\Chat;

use App\Exceptions\Ai\AiException;
use App\Models\ChatSession;
use Illuminate\Support\Facades\DB;

/**
 * DELETE session: ONE transaction bumps generation, marks the session deleted, clears the active slot and
 * erases every turn's text/answer/citations/food refs. Tombstone rows (sequence, status, hash) stay so a late
 * provider result fails TurnGate's generation CAS instead of resurrecting history.
 */
class SessionEraser
{
    public function __construct(private readonly TurnSlot $slot) {}

    /** @throws AiException NOT_FOUND when the session is already deleted or gone */
    public function erase(ChatSession $session): void
    {
        $this->slot->atomic(function () use ($session) {
            $now = now();
            $deleted = DB::table('chat_sessions')
                ->where('id', $session->getKey())->where('state', 'active')
                ->update([
                    'state' => 'deleted', 'generation' => DB::raw('generation + 1'), 'title' => null,
                    'active_turn_id' => null, 'active_token' => null, 'active_lease_expires_at' => null,
                    'last_food_id' => null, 'updated_at' => $now,
                ]);
            if ($deleted !== 1) {
                throw AiException::for('NOT_FOUND');
            }

            DB::table('chat_turns')->where('session_id', $session->getKey())->update([
                'message' => null, 'response' => null, 'citations' => null, 'food_refs' => null,
                'food_id' => null, 'amount_g' => null, 'mode' => null, 'updated_at' => $now,
            ]);
        });
    }
}
