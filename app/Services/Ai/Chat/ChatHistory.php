<?php

namespace App\Services\Ai\Chat;

use App\Exceptions\Ai\AiException;
use App\Models\ChatSession;
use App\Models\ChatTurn;
use App\Models\User;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Owner-scoped, cursor-paginated reads of sessions and turns (openapi Session / Turn shapes).
 * Turns older than ai.chat.retention_days are treated as gone before any purge job runs.
 */
class ChatHistory
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    /** @return array{data: list<array<string, mixed>>, next_cursor: string|null} */
    public function sessions(User $owner, ?string $cursor, int $limit): array
    {
        $scope = 'user:'.$owner->getKey();
        $position = HistoryCursor::decode($cursor, 'sessions', $scope);
        $query = ChatSession::query()->where('user_id', $owner->getKey())->where('state', 'active');
        if ($position !== null) {
            if (count($position) !== 2 || ! is_string($position[0]) || ! is_string($position[1])
                || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $position[0]) !== 1 || preg_match(self::UUID, $position[1]) !== 1) {
                throw AiException::for('INVALID_INPUT');
            }
            [$updatedAt, $id] = $position;
            $query->where(fn ($w) => $w->where('updated_at', '<', $updatedAt)
                ->orWhere(fn ($t) => $t->where('updated_at', $updatedAt)->where('id', '<', $id)));
        }

        $rows = $query->orderByDesc('updated_at')->orderByDesc('id')->limit($limit + 1)->get();
        $page = $rows->take($limit)->values();
        $last = $page->last();
        $next = $rows->count() > $limit && $last !== null
            ? HistoryCursor::encode('sessions', $scope, [(string) $last->getRawOriginal('updated_at'), (string) $last->id])
            : null;

        return ['data' => array_values(array_map(fn (ChatSession $s) => self::session($s), $page->all())), 'next_cursor' => $next];
    }

    /** @return array{data: list<array<string, mixed>>, next_cursor: string|null} */
    public function turns(User $owner, ChatSession $session, ?string $cursor, int $limit): array
    {
        $scope = 'user:'.$owner->getKey().'|session:'.$session->getKey();
        $position = HistoryCursor::decode($cursor, 'turns', $scope);
        $query = ChatTurn::query()->where('session_id', $session->getKey())
            ->where('created_at', '>=', TurnGate::retentionCutoff());
        if ($position !== null) {
            if (count($position) !== 1 || ! is_int($position[0]) || $position[0] < 0) {
                throw AiException::for('INVALID_INPUT');
            }
            $query->where('sequence', '>', $position[0]);
        }

        $rows = $query->orderBy('sequence')->limit($limit + 1)->get();
        $page = $rows->take($limit)->values();
        $last = $page->last();
        $next = $rows->count() > $limit && $last !== null
            ? HistoryCursor::encode('turns', $scope, [(int) $last->sequence])
            : null;

        return ['data' => array_values(array_map(fn (ChatTurn $t) => self::turn($t), $page->all())), 'next_cursor' => $next];
    }

    /** @return array{id: string, state: string, title: string|null, created_at: string, updated_at: string} */
    public static function session(ChatSession $session): array
    {
        return [
            'id' => (string) $session->id,
            'state' => 'active',
            'title' => $session->title,
            'created_at' => self::iso($session->created_at),
            'updated_at' => self::iso($session->updated_at),
        ];
    }

    /** @return array<string, mixed> openapi Turn; never the token, hash or internal metadata */
    public static function turn(ChatTurn $turn): array
    {
        return [
            'id' => (string) $turn->id,
            'client_turn_id' => (string) $turn->client_turn_id,
            'sequence' => (int) $turn->sequence,
            'state' => $turn->status,
            'user_content' => (string) ($turn->message ?? ''),
            'response' => $turn->status === TurnGate::COMPLETED ? $turn->response : null,
            'failure_code' => $turn->status === TurnGate::FAILED ? $turn->error_code : null,
            'created_at' => self::iso($turn->created_at),
        ];
    }

    private static function iso(?DateTimeInterface $at): string
    {
        return $at === null ? '' : DateTimeImmutable::createFromInterface($at)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
