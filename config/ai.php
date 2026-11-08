<?php

return [
    'enabled' => (bool) env('AI_ENABLED', false),

    'provider' => 'gemini',

    'gemini' => [
        'api_key' => env('AI_GEMINI_KEY'),
        'model' => env('AI_GEMINI_MODEL', 'gemini-3.8-flash'),
        'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        'thinking_level' => 'low',

        // Request storage stays disabled even when project logging changes.
        'store' => false,
    ],

    'http' => [
        'connect_timeout_seconds' => 5,
        'max_attempts' => 2,
    ],

    'context' => [
        'text_token_budget' => (int) env('AI_CONTEXT_TEXT_TOKEN_BUDGET', 6000),
        'history_turns' => 6,
    ],

    'scan' => [
        'deadline_seconds' => (int) env('AI_SCAN_DEADLINE_SECONDS', 25),
        'max_output_tokens' => (int) env('AI_OCR_MAX_OUTPUT_TOKENS', 4096),
        'max_bytes' => (int) env('AI_SCAN_MAX_BYTES', 5 * 1024 * 1024),
        'max_pixels' => (int) env('AI_SCAN_MAX_PIXELS', 16_000_000),
        'mime_types' => ['image/jpeg', 'image/png'],
    ],

    'chat' => [
        'deadline_seconds' => (int) env('AI_CHAT_DEADLINE_SECONDS', 20),
        'lease_seconds' => (int) env('AI_CHAT_LEASE_SECONDS', 35),
        'max_output_tokens' => (int) env('AI_CHAT_MAX_OUTPUT_TOKENS', 2048),
        'retention_days' => (int) env('AI_CHAT_RETENTION_DAYS', 7),
        'max_message_characters' => 2000,
        'max_amount_g' => 2000,
    ],

    'quotas' => [
        'scan' => [
            'per_minute' => 5,
            'per_day' => 60,
        ],
        'chat' => [
            'per_minute' => 10,
            'per_day' => 100,
        ],
        'submit' => [
            'per_minute' => 5,
            'per_day' => 30,
        ],
    ],
];
