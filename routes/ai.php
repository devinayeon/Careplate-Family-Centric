<?php

use App\Http\Controllers\Admin\FoodReviewController;
use App\Http\Controllers\Ai\ChatSessionController;
use App\Http\Controllers\Ai\ChatTurnController;
use App\Http\Controllers\Ai\FoodController;
use App\Http\Controllers\Ai\ScanController;
use App\Http\Middleware\Ai\AiResponseHeaders;
use Illuminate\Support\Facades\Route;

// Loaded from routes/web.php so the group shares the web session, cookie and CSRF stack.
Route::prefix('ai/v1')
    ->name('ai.')
    ->middleware(['web', 'auth', AiResponseHeaders::class])
    ->group(function (): void {
        // Real order: Authenticate > ThrottleRequests > SubstituteBindings > Permission > Authorize. Throttle runs before the permission alias, so a 403 user spends their own scan quota; both run before any provider call.
        Route::post('scan', ScanController::class)
            ->middleware(['permission:health_tool:use_scanner', 'throttle:ai-scan'])
            ->name('scan');

        // Permission AND admin role (ahli_gizi and nutritionist have neither). Foods arrive only through the team's `makanan` (ai:sync-makanan) or the catalog seeder: there is no user submission endpoint. Ids stay plain strings: no model binding before the gate.
        Route::prefix('admin')
            ->name('admin.')
            ->middleware(['permission:food:verify', 'role:admin'])
            ->group(function (): void {
                Route::post('food-submissions/{submission}/approve', [FoodReviewController::class, 'approve'])
                    ->whereUuid('submission')->name('food-submissions.approve');
                Route::post('foods/{food}/revoke', [FoodReviewController::class, 'revoke'])
                    ->whereUuid('food')->name('foods.revoke');
            });

        Route::get('foods', FoodController::class)
            ->middleware('permission:health_tool:use_ai_chat')
            ->name('foods.index');

        // {session} binds through ChatSessionPolicy: foreign or deleted sessions are 404, never 403.
        Route::prefix('chat/sessions')
            ->name('chat.sessions.')
            ->middleware('permission:health_tool:use_ai_chat')
            ->group(function (): void {
                Route::post('/', [ChatSessionController::class, 'store'])->name('store');
                Route::get('/', [ChatSessionController::class, 'index'])->name('index');
                Route::delete('{session}', [ChatSessionController::class, 'destroy'])
                    ->middleware('can:delete,session')->name('destroy');
                Route::get('{session}/turns', [ChatTurnController::class, 'index'])
                    ->middleware('can:view,session')->name('turns.index');
                // Throttle runs before the policy (SubstituteBindings > Authorize), so a POST to a foreign session is 404 but still spends the caller's own quota; the quota 429 comes before any provider call.
                Route::post('{session}/turns', [ChatTurnController::class, 'store'])
                    ->middleware(['can:view,session', 'throttle:ai-chat'])->name('turns.store');
            });
    });
