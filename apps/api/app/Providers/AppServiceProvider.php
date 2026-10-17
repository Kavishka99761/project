<?php

namespace App\Providers;

use App\Services\Platform\DataAudit\DataAuditInstaller;
use App\Support\Activity\ActivityContext;
use App\Support\Activity\DatabaseAuditSession;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Support\Facades\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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
        $this->configureDatabaseAudit();
    }

    /**
     * Keep the SQL Server audit triggers in step with the schema, and
     * attribute changes made outside HTTP (artisan, scheduler, queue).
     */
    private function configureDatabaseAudit(): void
    {
        Event::listen(MigrationsStarted::class, function () {
            app(DataAuditInstaller::class)->uninstall();
        });
        Event::listen(MigrationsEnded::class, function () {
            app(DataAuditInstaller::class)->install();
        });

        if ($this->app->runningInConsole()) {
            Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event) {
                $command = $_SERVER['argv'][1] ?? 'console';
                $source = str_starts_with($command, 'queue:') ? 'queue' : 'console';
                DatabaseAuditSession::begin($source, connection: $event->connection);
            });
        }
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(300)
            ->by($request->user()?->id ?: $request->ip()));

        // Brute-force protection: per email + IP.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute((int) env('AUTH_LOGIN_ATTEMPTS', 5))
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));

        // Sending a code: tight per address as well as per IP, so nobody can be
        // mail-bombed and no mailbox can be used to probe for accounts.
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perMinutes(10, 5)->by('auth-code:'.Str::lower((string) $request->input('email'))),
        ]);

        // Checking a code: the 5-guess cap lives in AuthCodeService; this only
        // stops someone hammering the endpoint from one place.
        RateLimiter::for('code-check', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
            Limit::perMinute(10)->by('auth-code-check:'.Str::lower((string) $request->input('email'))),
        ]);

        RateLimiter::for('chat', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('exports', fn (Request $request) => Limit::perMinute(40)->by($request->user()?->id ?: $request->ip()));
    }
}
