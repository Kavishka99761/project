<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API always answers in JSON (validation errors included), regardless
 * of the Accept header a client sends.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
