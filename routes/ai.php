<?php

use App\Http\Controllers\Admin\FoodReviewController;
use App\Http\Controllers\Ai\ChatSessionController;
use App\Http\Controllers\Ai\ChatTurnController;
use App\Http\Controllers\Ai\FoodController;
use App\Http\Controllers\Ai\FoodSubmissionController;
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

        // Submission only creates a pending row; publishing is admin-only below and always goes through ReviewFood.
        // The per-user throttle bounds pending-row spam (observed: it runs before the permission alias, so a 403 user only spends their own bucket).
        Route::post('food-submissions', [FoodSubmissionController::class, 'store'])
            ->middleware(['permission:submit-food', 'throttle:ai-submit'])
            ->name('food-submissions.store');

        // Permission AND admin role (nutritionist has neither). Ids stay plain strings: no model binding before the gate.
        Route::prefix('admin')
            ->name('admin.')
            ->middleware(['permission:verify-food', 'role:admin'])
            ->group(function (): void {
                Route::post('food-submissions/{submission}/approve', [FoodReviewController::class, 'approve'])
                    ->whereUuid('submission')->name('food-submissions.approve');
                Route::post('foods/{food}/revoke', [FoodReviewController::class, 'revoke'])
                    ->whereUuid('food')->name('foods.revoke');
            });

        Route::get('foods', FoodController::class)
            ->middleware('permission:nutrition-chat')
            ->name('foods.index');

        // {session} binds through ChatSessionPolicy: foreign or deleted sessions are 404, never 403.
        Route::prefix('chat/sessions')
            ->name('chat.sessions.')
            ->middleware('permission:nutrition-chat')
            ->group(function (): void {
                Route::post('/', [ChatSessionController::class, 'store'])->name('store');
                Route::get('/', [ChatSessionController::class, 'index'])->name('index');
                Route::delete('{session}', [ChatSessionController::class, 'destroy'])
                    ->middleware('can:delete,session')->name('destroy');
                Route::get('{session}/turns', [ChatTurnController::class, 'index'])
                    ->middleware('can:view,session')->name('turns.index');
                // Policy before throttle: a foreign session is 404 and spends no quota; the quota 429 comes before any provider call.
                Route::post('{session}/turns', [ChatTurnController::class, 'store'])
                    ->middleware(['can:view,session', 'throttle:ai-chat'])->name('turns.store');
            });
    });
