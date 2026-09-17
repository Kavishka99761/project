<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/**
 * Laravel 11 application bootstrap.
 *
 * The api routes file is registered here, which automatically prefixes every
 * route with /api and applies the `api` middleware group. Authentication uses
 * Sanctum bearer tokens (see routes/api.php), so no SPA session cookie setup is
 * required — the web and mobile clients both send `Authorization: Bearer <token>`.
 */
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Route model binding + the `api` group are configured by the framework.
        // Nothing extra is required for the token-based JSON API.
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
