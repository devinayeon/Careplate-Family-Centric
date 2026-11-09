<?php

namespace App\Services\Ai\Gemini;

use DomainException;
use JsonException;

/**
 * Turns a 2xx OpenAI-compatible chat/completions body into the final JSON or throws a
 * DomainException whose message is one of our own fixed reason strings (never provider text).
 * Same output shape as GeminiResponseParser. Reasoning text is ignored; only `content` counts.
 */
final class OpenAiResponseParser
{
    /**
     * @param  mixed  $data  decoded HTTP body
     * @return array{json: array<mixed>, finishReason: string, modelVersion: ?string, usage: array{prompt: ?int, candidates: ?int, thoughts: ?int, total: ?int}}
     */
    public static function parse(mixed $data): array
    {
        if (! is_array($data)) {
            throw new DomainException('body_not_json');
        }
        $choice = $data['choices'][0] ?? null;
        if (! is_array($choice)) {
            throw new DomainException('no_candidate');
        }

        $finish = $choice['finish_reason'] ?? null;
        if ($finish !== 'stop') {
            $safe = is_string($finish) && preg_match('/^[a-z_]{1,40}$/', $finish) === 1 ? strtoupper($finish) : 'MISSING';
            throw new DomainException('finish_'.$safe);
        }

        $message = is_array($choice['message'] ?? null) ? $choice['message'] : [];
        if (! empty($message['tool_calls']) || ! empty($message['function_call'])) {
            throw new DomainException('tool_call');
        }
        $text = $message['content'] ?? null;
        if (! is_string($text) || trim($text) === '') {
            throw new DomainException('empty_text');
        }

        try {
            $json = json_decode($text, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new DomainException('invalid_json');
        }
        if (! is_array($json)) {
            throw new DomainException('json_not_object');
        }

        return [
            'json' => $json,
            'finishReason' => 'STOP',
            'modelVersion' => self::modelVersion($data['model'] ?? null),
            'usage' => self::usage(is_array($data['usage'] ?? null) ? $data['usage'] : []),
        ];
    }

    /**
     * `completion_tokens` includes reasoning tokens here; report them separately like Gemini does.
     *
     * @param  array<string, mixed>  $usage
     * @return array{prompt: ?int, candidates: ?int, thoughts: ?int, total: ?int}
     */
    private static function usage(array $usage): array
    {
        $details = is_array($usage['completion_tokens_details'] ?? null) ? $usage['completion_tokens_details'] : [];
        $completion = self::count($usage['completion_tokens'] ?? null);
        $reasoning = self::count($details['reasoning_tokens'] ?? null);

        return [
            'prompt' => self::count($usage['prompt_tokens'] ?? null),
            'candidates' => $completion !== null && $reasoning !== null ? max(0, $completion - $reasoning) : $completion,
            'thoughts' => $reasoning,
            'total' => self::count($usage['total_tokens'] ?? null),
        ];
    }

    private static function count(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 ? $value : null;
    }

    private static function modelVersion(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9._:\/\-]{1,100}$/', $value) === 1 ? $value : null;
    }
}
