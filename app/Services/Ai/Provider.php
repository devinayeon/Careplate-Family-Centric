<?php

namespace App\Services\Ai;

/**
 * Settings of the active model provider (`ai.provider`): `openai` is any OpenAI-compatible
 * chat/completions gateway (Qwen), `gemini` is the Google generateContent API.
 */
final class Provider
{
    public static function isOpenAi(): bool
    {
        return config('ai.provider') === 'openai';
    }

    public static function name(): string
    {
        return self::isOpenAi() ? 'openai' : 'gemini';
    }

    public static function apiKey(): string
    {
        return (string) config('ai.'.self::name().'.api_key', '');
    }

    public static function model(): string
    {
        return (string) config('ai.'.self::name().'.model', '');
    }

    public static function baseUrl(): string
    {
        return rtrim((string) config('ai.'.self::name().'.base_url', ''), '/');
    }
}
