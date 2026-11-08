<?php

use App\Http\Middleware\Ai\AiResponseHeaders;
use Illuminate\Support\Facades\Route;

// Loaded from routes/web.php so the group shares the web session, cookie and CSRF stack.
Route::prefix('ai/v1')
    ->name('ai.')
    ->middleware(['web', 'auth', AiResponseHeaders::class])
    ->group(function (): void {
        // Scan, chat and food routes are added by later tasks.
    });
