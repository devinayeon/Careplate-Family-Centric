<?php

namespace App\Services\Ai\Eval;

use App\Services\Ai\Gemini\GeminiClient;
use App\Services\Ai\Gemini\GeminiResult;

/**
 * GeminiClient that remembers the last successful result, so the eval command can record
 * usage and model version even when the pipeline later rejects the reading.
 */
class RecordingGeminiClient extends GeminiClient
{
    public ?GeminiResult $last = null;

    public function generate(string $feature, string $systemPrompt, array $parts, array $responseSchema, int $maxOutputTokens, int $deadlineSeconds): GeminiResult
    {
        return $this->last = parent::generate($feature, $systemPrompt, $parts, $responseSchema, $maxOutputTokens, $deadlineSeconds);
    }
}
