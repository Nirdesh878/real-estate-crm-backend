<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('meta-refresh', function (Request $request) {
            $userId = optional($request->user())->id;
            return Limit::perMinute(3)->by($userId ?: $request->ip());
        });

        RateLimiter::for('whatsapp-send', function (Request $request) {
            $userId = optional($request->user())->id;
            return Limit::perMinute(2)->by($userId ?: $request->ip());
        });
    }
}
