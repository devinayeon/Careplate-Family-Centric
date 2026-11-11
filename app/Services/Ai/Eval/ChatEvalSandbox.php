<?php

namespace App\Services\Ai\Eval;

use App\Models\ChatSession;
use App\Models\ChatTurn;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The temporary rows of one chat eval run: family_head users, the canary session, and the cleanup that deletes
 * them and proves nothing is left behind.
 */
final class ChatEvalSandbox
{
    /** @var list<int> ids of the temporary users, whatever happens to the run */
    private array $userIds = [];

    public function user(): User
    {
        $user = User::create(['name' => 'AI Eval', 'email' => 'eval-'.bin2hex(random_bytes(6)).'@eval.invalid', 'password' => Str::random(40)]);
        $this->userIds[] = $user->id;
        $user->assignRole('family_head');
        if (! $user->can('nutrition-chat')) {
            throw new RuntimeException('The family_head role lacks the nutrition-chat permission; run the permission seeder.');
        }

        return $user;
    }

    /** A second user with a finished turn whose text must never show up in the first user's answer. */
    public function plantCanary(): string
    {
        $canary = 'CANARY-'.bin2hex(random_bytes(6));
        $session = ChatSession::create(['user_id' => $this->user()->id, 'title' => null, 'state' => 'active', 'generation' => 1, 'next_sequence' => 2]);
        ChatTurn::create([
            'session_id' => $session->id, 'client_turn_id' => (string) Str::uuid(), 'sequence' => 1, 'request_hash' => 'eval', 'status' => 'completed',
            'token' => Str::random(40), 'generation' => 1, 'lease_expires_at' => now(), 'message' => $canary,
            'response' => ['answer' => $canary], 'citations' => [], 'food_refs' => [],
        ]);

        return $canary;
    }

    /** Deletes the temporary users with their sessions and turns, then proves nothing is left. */
    public function cleanup(): void
    {
        if ($this->userIds === []) {
            return;
        }
        $sessions = ChatSession::query()->whereIn('user_id', $this->userIds)->pluck('id')->all();
        ChatTurn::query()->whereIn('session_id', $sessions)->delete();
        ChatSession::query()->whereIn('id', $sessions)->delete();
        User::query()->whereIn('id', $this->userIds)->get()->each->delete();

        if (User::query()->whereIn('id', $this->userIds)->exists() || ChatSession::query()->whereIn('user_id', $this->userIds)->exists()
            || ChatTurn::query()->whereIn('session_id', $sessions)->exists()) {
            throw new RuntimeException('Eval cleanup left temporary rows behind.');
        }
        $this->userIds = [];
    }
}
