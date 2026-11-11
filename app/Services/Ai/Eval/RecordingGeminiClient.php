<?php

namespace App\Services\Ai\Eval;

use App\Services\Ai\Gemini\GeminiClient;
use App\Services\Ai\Gemini\GeminiResult;

/**
 * GeminiClient that remembers successful results, so the eval commands can record usage and model version
 * even when the pipeline later rejects the reading, and counts every generate() call (including failed ones)
 * so the chat eval can prove that server-template modes made no model call.
 */
class RecordingGeminiClient extends GeminiClient
{
    public ?GeminiResult $last = null;

    /** @var list<GeminiResult> successful results since the last reset() */
    public array $results = [];

    /** generate() calls since the last reset(), failed ones included */
    public int $calls = 0;

    public function reset(): void
    {
        $this->last = null;
        $this->results = [];
        $this->calls = 0;
    }

    public function generate(string $feature, string $systemPrompt, array $parts, array $responseSchema, int $maxOutputTokens, int $deadlineSeconds): GeminiResult
    {
        $this->calls++;
        $result = parent::generate($feature, $systemPrompt, $parts, $responseSchema, $maxOutputTokens, $deadlineSeconds);
        $this->results[] = $result;

        return $this->last = $result;
    }
}
