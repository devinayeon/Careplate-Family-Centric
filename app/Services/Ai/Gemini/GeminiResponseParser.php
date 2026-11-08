<?php

namespace App\Services\Ai\Gemini;

use DomainException;
use JsonException;

/**
 * Turns a 2xx generateContent body into the final JSON or throws a DomainException
 * whose message is one of our own fixed reason strings (never provider text).
 */
final class GeminiResponseParser
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
        if (! empty($data['promptFeedback']['blockReason'])) {
            throw new DomainException('prompt_blocked');
        }
        $candidate = $data['candidates'][0] ?? null;
        if (! is_array($candidate)) {
            throw new DomainException('no_candidate');
        }

        $finish = $candidate['finishReason'] ?? null;
        if ($finish !== 'STOP') {
            // Provider enum: only echoed when it has the expected shape.
            $safe = is_string($finish) && preg_match('/^[A-Z_]{1,40}$/', $finish) === 1 ? $finish : 'MISSING';
            throw new DomainException('finish_'.$safe);
        }

        $text = '';
        foreach ($candidate['content']['parts'] ?? [] as $part) {
            if (! is_array($part)) {
                throw new DomainException('bad_part');
            }
            if (isset($part['functionCall'])) {
                throw new DomainException('tool_call');
            }
            if (isset($part['text']) && is_string($part['text']) && empty($part['thought'])) {
                $text .= $part['text'];
            }
        }
        if (trim($text) === '') {
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

        $usage = is_array($data['usageMetadata'] ?? null) ? $data['usageMetadata'] : [];

        return [
            'json' => $json,
            'finishReason' => 'STOP',
            'modelVersion' => self::modelVersion($data['modelVersion'] ?? null),
            'usage' => [
                'prompt' => self::count($usage['promptTokenCount'] ?? null),
                'candidates' => self::count($usage['candidatesTokenCount'] ?? null),
                'thoughts' => self::count($usage['thoughtsTokenCount'] ?? null),
                'total' => self::count($usage['totalTokenCount'] ?? null),
            ],
        ];
    }

    private static function count(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 ? $value : null;
    }

    private static function modelVersion(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9._:\-]{1,100}$/', $value) === 1 ? $value : null;
    }
}
