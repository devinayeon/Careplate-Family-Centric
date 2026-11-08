<?php

use App\Http\Controllers\Ai\ScanController;
use App\Http\Middleware\Ai\AiResponseHeaders;
use Illuminate\Support\Facades\Route;

// Loaded from routes/web.php so the group shares the web session, cookie and CSRF stack.
Route::prefix('ai/v1')
    ->name('ai.')
    ->middleware(['web', 'auth', AiResponseHeaders::class])
    ->group(function (): void {
        // Permission before throttle: only allowed users spend scan quota; both run before any provider call.
        Route::post('scan', ScanController::class)
            ->middleware(['permission:scan-nutrition', 'throttle:ai-scan'])
            ->name('scan');

        // Chat and food routes are added by later tasks.
    });
