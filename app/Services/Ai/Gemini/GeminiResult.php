<?php

namespace App\Services\Ai\Gemini;

/**
 * Validated outcome of one successful generateContent call (finishReason STOP, JSON decoded).
 */
final class GeminiResult
{
    /**
     * @param  array<mixed>  $json  decoded model JSON; schema/semantic validation is the caller's job
     * @param  array{prompt: ?int, candidates: ?int, thoughts: ?int, total: ?int}  $usage  null = provider did not report it
     */
    public function __construct(
        public readonly array $json,
        public readonly ?string $modelVersion,
        public readonly array $usage,
        public readonly int $latencyMs,
        public readonly string $finishReason = 'STOP',
    ) {}
}
