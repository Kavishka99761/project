<?php

use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\RecordActivity;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
|--------------------------------------------------------------------------
| EDU-SMART API bootstrap (Laravel 12)
|--------------------------------------------------------------------------
| Routes live under /api/v1 (routes/api.php). Authentication is stateless:
| the React client sends a Sanctum bearer token with every request.
*/

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [ForceJsonResponse::class], append: [RecordActivity::class]);
        $middleware->append(SecurityHeaders::class);
        $middleware->throttleApi('api');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                $missingModel = $e->getPrevious() instanceof ModelNotFoundException;

                return response()->json([
                    'message' => $missingModel ? 'The requested record was not found.' : 'This endpoint does not exist.',
                ], 404);
            }
        });

        // Business-rule violations raised by services ("session already running", …).
        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Your session has expired. Please sign in again.'], 401);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Too many requests. Please wait a moment and try again.',
                    'retry_after' => (int) ($e->getHeaders()['Retry-After'] ?? 60),
                ], 429, $e->getHeaders());
            }
        });

        $exceptions->dontReport([DomainException::class]);
    })->create();
