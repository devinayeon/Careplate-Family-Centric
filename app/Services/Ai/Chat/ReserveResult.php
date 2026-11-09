<?php

namespace App\Services\Ai\Chat;

use App\Exceptions\Ai\AiException;
use App\Models\ChatTurn;

/**
 * Outcome of TurnGate::reserve. NEW: a pending turn the caller now owns (call finalize or fail).
 * REPLAY: an existing terminal turn for the same key; completed => response(), failed => error().
 * Every other outcome (409/404) is thrown by reserve as an AiException.
 */
final class ReserveResult
{
    public const NEW = 'new';

    public const REPLAY = 'replay';

    private function __construct(public readonly string $kind, public readonly ChatTurn $turn) {}

    public static function fresh(ChatTurn $turn): self
    {
        return new self(self::NEW, $turn);
    }

    public static function replay(ChatTurn $turn): self
    {
        return new self(self::REPLAY, $turn);
    }

    public function isNew(): bool
    {
        return $this->kind === self::NEW;
    }

    public function isReplay(): bool
    {
        return $this->kind === self::REPLAY;
    }

    /**
     * Stored ChatResponse of a completed replay; null otherwise.
     *
     * @return array<string, mixed>|null
     */
    public function response(): ?array
    {
        return $this->isReplay() && $this->turn->status === TurnGate::COMPLETED ? $this->turn->response : null;
    }

    /** Stored terminal error of a failed replay (throw it); null otherwise. */
    public function error(): ?AiException
    {
        return $this->isReplay() && $this->turn->status === TurnGate::FAILED
            ? AiException::for((string) $this->turn->error_code)
            : null;
    }
}
