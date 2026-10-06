<?php

namespace App\Providers;

use App\Support\Activity\ActivityContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One activity record per request (reset between requests/jobs).
        $this->app->scoped(ActivityContext::class);
    }

    public function boot(): void
    {
        // Surface N+1 queries in the log during development (never break a request).
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) {
            logger()->debug(sprintf('Lazy loaded %s::%s', $model::class, $relation));
        });

        JsonResource::withoutWrapping();

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(300)
            ->by($request->user()?->id ?: $request->ip()));

        // Brute-force protection: per email + IP.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute((int) env('AUTH_LOGIN_ATTEMPTS', 5))
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));

        RateLimiter::for('chat', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('exports', fn (Request $request) => Limit::perMinute(40)->by($request->user()?->id ?: $request->ip()));
    }
}
