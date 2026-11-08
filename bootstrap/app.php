<?php

use App\Exceptions\Ai\AiErrorRenderer;
use App\Exceptions\Ai\AiException;
use App\Http\Middleware\Ai\AiResponseHeaders;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'ai/*') || $request->expectsJson(),
        );

        // Expected AI failures render as envelopes; reporting them would log traces of label-handling frames.
        $exceptions->dontReport(AiException::class);
        $exceptions->render(AiErrorRenderer::render(...));

        // A database error carries its bindings (chat text, submission JSON) in its message: on /ai/* log only an allowlist.
        $exceptions->report(function (Throwable $e) {
            if (! ($e instanceof QueryException || $e instanceof PDOException) || ! request()->is('ai/*')) {
                return null;
            }
            Log::error('ai.database_error', [
                'exception' => $e::class,
                'code' => (string) ($e instanceof QueryException ? ($e->errorInfo[1] ?? $e->getCode()) : $e->getCode()),
                'request_id' => AiResponseHeaders::requestId(request()),
            ]);

            return false;
        });
    })->create();
