<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        foreach (['scan', 'chat'] as $feature) {
            // Distinct keys per window: Laravel counts hits per key, so sharing one would merge them.
            RateLimiter::for('ai-'.$feature, fn (Request $request) => [
                Limit::perMinute((int) config("ai.quotas.$feature.per_minute"))->by("ai:$feature:minute:".self::quotaKey($request)),
                Limit::perDay((int) config("ai.quotas.$feature.per_day"))->by("ai:$feature:day:".self::quotaKey($request)),
            ]);
        }
    }

    private static function quotaKey(Request $request): string
    {
        return (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());
    }
}
