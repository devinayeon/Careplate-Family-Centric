<?php

namespace App\Policies;

use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Chat sessions are visible only to their owner while active. Everyone else, admins included, gets 404
 * (never 403) so a UUID never confirms that another account's session exists.
 */
class ChatSessionPolicy
{
    public function view(User $user, ChatSession $session): Response
    {
        return $this->owned($user, $session);
    }

    public function delete(User $user, ChatSession $session): Response
    {
        return $this->owned($user, $session);
    }

    private function owned(User $user, ChatSession $session): Response
    {
        return (string) $session->user_id === (string) $user->getKey() && $session->state === 'active'
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
