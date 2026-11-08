<?php

namespace App\Exceptions\Ai;

use App\Http\Middleware\Ai\AiResponseHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\OriginMismatchException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Turns every failure on /ai/* into the contract envelope. Never exposes the
 * original message, trace, SQL or validation detail of a non-AI exception.
 */
class AiErrorRenderer
{
    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('ai/*')) {
            return null;
        }

        return self::toAiException($e)->toResponse(AiResponseHeaders::requestId($request));
    }

    public static function toAiException(Throwable $e): AiException
    {
        if ($e instanceof AiException) {
            return $e;
        }
        if ($e instanceof AuthenticationException) {
            return AiException::for('UNAUTHENTICATED');
        }
        if ($e instanceof TokenMismatchException || $e instanceof OriginMismatchException) {
            return AiException::for('CSRF_MISMATCH');
        }
        if ($e instanceof AuthorizationException) {
            return AiException::for('FORBIDDEN');
        }
        if ($e instanceof ModelNotFoundException) {
            return AiException::for('NOT_FOUND');
        }
        if ($e instanceof ValidationException) {
            return AiException::for('INVALID_INPUT');
        }
        if ($e instanceof HttpExceptionInterface) {
            // Laravel already converted CSRF/authorization/model-not-found into HTTP exceptions.
            return match ($e->getStatusCode()) {
                403 => AiException::for('FORBIDDEN'),
                404, 405 => AiException::for('NOT_FOUND'),
                413 => AiException::for('IMAGE_TOO_LARGE'),
                419 => AiException::for('CSRF_MISMATCH'),
                429 => AiException::for('RATE_LIMITED', headers: array_intersect_key($e->getHeaders(), ['Retry-After' => 1])),
                default => AiException::for('INTERNAL'),
            };
        }

        return AiException::for('INTERNAL');
    }
}
