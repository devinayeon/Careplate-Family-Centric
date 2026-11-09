<?php

namespace App\Console\Commands\Ai;

use App\Exceptions\Ai\AiException;
use App\Services\Ai\Gemini\GeminiClient;
use App\Services\Ai\Provider;
use Illuminate\Console\Command;

class Preflight extends Command
{
    protected $signature = 'ai:preflight';

    protected $description = 'Send one tiny JSON request to the configured model and print sanitized metadata.';

    public function handle(GeminiClient $client): int
    {
        $model = Provider::model();

        try {
            $result = $client->generate(
                'preflight',
                'Reply with JSON only, following the schema.',
                [['text' => 'Set ok to true.']],
                [
                    'type' => 'object',
                    'properties' => ['ok' => ['type' => 'boolean']],
                    'required' => ['ok'],
                    'additionalProperties' => false,
                ],
                512,
                20,
            );
        } catch (AiException $e) {
            $this->line('ok: false');
            $this->line("model: {$model}");
            $this->line("error: {$e->errorCode}");
            $this->line('http_status: '.($client->lastHttpStatus ?? 'n/a'));
            $this->line('reason: '.($client->lastReason ?? 'n/a'));

            return self::FAILURE;
        }

        $usage = collect($result->usage)
            ->map(fn (?int $v, string $k) => $k.'='.($v ?? 'unknown'))
            ->implode(' ');

        $this->line('ok: '.(($result->json['ok'] ?? null) === true ? 'true' : 'false'));
        $this->line("model: {$model}");
        $this->line('modelVersion: '.($result->modelVersion ?? 'unknown'));
        $this->line("latency_ms: {$result->latencyMs}");
        $this->line("usage: {$usage}");
        $this->line("finishReason: {$result->finishReason}");

        return self::SUCCESS;
    }
}
