<?php

namespace App\Providers;

use App\Services\FirebaseService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Boot FirebaseService once per request lifecycle.
        $this->app->singleton(FirebaseService::class);
    }

    public function boot(): void
    {
        // Sanctum personal access tokens expire after 30 days.
        config(['sanctum.expiration' => 60 * 24 * 30]);
    }
}
