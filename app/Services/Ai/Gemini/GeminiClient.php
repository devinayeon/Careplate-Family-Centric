<?php

namespace App\Services\Ai\Gemini;

use App\Exceptions\Ai\AiException;
use App\Http\Middleware\Ai\AiResponseHeaders;
use App\Services\Ai\Provider;
use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Single REST adapter for the active provider: Gemini generateContent or an OpenAI-compatible
 * chat/completions gateway (Qwen), chosen by `ai.provider`. One generation, at most one
 * transient retry inside a shared deadline, no JSON repair, no model switching.
 * Logs allowlisted metadata only; provider error text never leaves this class.
 */
class GeminiClient
{
    private const MIN_RETRY_SECONDS = 1.0;

    private const DEFAULT_BACKOFF_SECONDS = 0.5;

    /** Provider HTTP status of the last attempt (diagnostics for ai:preflight). */
    public ?int $lastHttpStatus = null;

    /** Fixed reason string of the last failure (never provider text). */
    public ?string $lastReason = null;

    /**
     * @param  array<int, array<string, mixed>>  $parts  Gemini `parts` (text / inlineData)
     * @param  array<string, mixed>  $responseSchema  provider JSON schema
     *
     * @throws AiException
     */
    public function generate(string $feature, string $systemPrompt, array $parts, array $responseSchema, int $maxOutputTokens, int $deadlineSeconds): GeminiResult
    {
        $start = hrtime(true);
        $this->lastHttpStatus = $this->lastReason = null;
        $attempts = 0;

        try {
            $url = $this->endpoint();
            $body = $this->body($systemPrompt, $parts, $responseSchema, $maxOutputTokens);
            $result = $this->run($url, $body, $start, (float) $deadlineSeconds, $attempts);
        } catch (AiException $e) {
            $this->log('warning', $feature, $e->errorCode, $start, $attempts);
            throw $e;
        }
        $this->log('info', $feature, 'ok', $start, $attempts, $result);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function run(string $url, array $body, int $start, float $deadline, int &$attempts): GeminiResult
    {
        $maxAttempts = max(1, min(2, (int) config('ai.http.max_attempts', 2)));
        while (true) {
            $remaining = $deadline - $this->elapsed($start);
            if ($remaining <= 0) {
                throw $this->fail('AI_TIMEOUT', 'deadline');
            }
            $attempts++;

            $retryAfter = null;
            $response = null;
            try {
                $response = Http::withHeaders(Provider::isOpenAi() ? ['Authorization' => 'Bearer '.Provider::apiKey()] : ['x-goog-api-key' => Provider::apiKey()])
                    ->acceptJson()
                    ->asJson()
                    ->connectTimeout(min((float) config('ai.http.connect_timeout_seconds', 5), $remaining))
                    ->timeout($remaining)
                    ->post($url, $body);
            } catch (ConnectionException $e) {
                // A timeout after the request went out may still bill; never retry it.
                if (stripos($e->getMessage(), 'cURL error 28') !== false || stripos($e->getMessage(), 'timed out') !== false) {
                    throw $this->fail('AI_TIMEOUT', 'transport_timeout');
                }
                $this->lastReason = 'connection_error';
            } catch (Throwable) {
                throw $this->fail('AI_PROVIDER_ERROR', 'transport_error');
            }

            if ($response !== null) {
                $this->lastHttpStatus = $response->status();
                if ($response->successful()) {
                    $this->lastReason = null;

                    return $this->result($response, $start);
                }
                $this->lastReason = 'http_error';
                if ($response->status() !== 429 && $response->status() < 500) {
                    throw $this->fail('AI_PROVIDER_ERROR', 'http_error');
                }
                $retryAfter = $this->retryAfter($response);
            }

            $wait = $retryAfter ?? self::DEFAULT_BACKOFF_SECONDS;
            if ($attempts >= $maxAttempts || $deadline - $this->elapsed($start) - $wait < self::MIN_RETRY_SECONDS) {
                throw $this->fail('AI_PROVIDER_ERROR', $this->lastReason ?? 'provider_error');
            }
            Sleep::usleep((int) round($wait * 1_000_000));
        }
    }

    private function result(Response $response, int $start): GeminiResult
    {
        try {
            $parsed = Provider::isOpenAi() ? OpenAiResponseParser::parse($response->json()) : GeminiResponseParser::parse($response->json());
        } catch (DomainException $e) {
            throw $this->fail('AI_OUTPUT_INVALID', $e->getMessage());
        }

        return new GeminiResult($parsed['json'], $parsed['modelVersion'], $parsed['usage'], $this->latencyMs($start), $parsed['finishReason']);
    }

    private function fail(string $code, string $reason): AiException
    {
        $this->lastReason = $reason;

        return AiException::for($code);
    }

    private function endpoint(): string
    {
        $key = Provider::apiKey();
        if (! config('ai.enabled') || trim($key) === '') {
            throw $this->fail('AI_DISABLED', 'disabled');
        }
        $model = Provider::model();
        if (preg_match(Provider::isOpenAi() ? '/^[A-Za-z0-9._\/-]{1,100}$/' : '/^[A-Za-z0-9._-]{1,100}$/', $model) !== 1) {
            throw $this->fail('POLICY_CONFIG_INVALID', 'bad_model_id');
        }

        return Provider::isOpenAi() ? Provider::baseUrl().'/chat/completions' : Provider::baseUrl().'/models/'.$model.':generateContent';
    }

    /**
     * @param  array<int, array<string, mixed>>  $parts
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function body(string $systemPrompt, array $parts, array $schema, int $maxOutputTokens): array
    {
        if (Provider::isOpenAi()) {
            return $this->openAiBody($systemPrompt, $parts, $schema, $maxOutputTokens);
        }

        return [
            'store' => false,
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => [['role' => 'user', 'parts' => $parts]],
            'generationConfig' => [
                'thinkingConfig' => ['thinkingLevel' => (string) config('ai.gemini.thinking_level', 'low')],
                'maxOutputTokens' => $maxOutputTokens,
                'responseFormat' => ['text' => ['mimeType' => 'APPLICATION_JSON', 'schema' => $schema]],
            ],
        ];
    }

    /**
     * OpenAI-compatible body: Gemini-style parts become chat content (text / image data URL).
     * No provider storage flag exists here; retention is the gateway's policy.
     *
     * @param  array<int, array<string, mixed>>  $parts
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function openAiBody(string $systemPrompt, array $parts, array $schema, int $maxOutputTokens): array
    {
        $content = [];
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $content[] = ['type' => 'text', 'text' => (string) $part['text']];
            } elseif (isset($part['inlineData'])) {
                $content[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$part['inlineData']['mimeType'].';base64,'.$part['inlineData']['data']]];
            }
        }

        return [
            'model' => Provider::model(),
            'max_tokens' => $maxOutputTokens,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $content],
            ],
            'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'result', 'schema' => $schema]],
        ];
    }

    private function retryAfter(Response $response): ?float
    {
        $value = trim((string) $response->header('Retry-After'));
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return (float) $value;
        }
        $time = strtotime($value);

        return $time === false ? null : max(0.0, (float) ($time - time()));
    }

    private function elapsed(int $start): float
    {
        return (hrtime(true) - $start) / 1e9;
    }

    private function latencyMs(int $start): int
    {
        return (int) round($this->elapsed($start) * 1000);
    }

    private function log(string $level, string $feature, string $status, int $start, int $attempts, ?GeminiResult $result = null): void
    {
        $requestId = request()->attributes->get(AiResponseHeaders::REQUEST_ID);

        Log::log($level, 'ai.gemini', array_filter([
            'request_id' => is_string($requestId) ? $requestId : null,
            'feature' => $feature,
            'status' => $status,
            'http_status' => $this->lastHttpStatus,
            'reason' => $this->lastReason,
            'attempts' => $attempts,
            'latency_ms' => $this->latencyMs($start),
            'model' => Provider::model(),
            'model_version' => $result?->modelVersion,
            'usage' => $result?->usage,
        ], fn ($v) => $v !== null));
    }
}
