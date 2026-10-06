<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hardening headers for every API response (OWASP secure headers).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('Cross-Origin-Resource-Policy', 'cross-origin');
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
        }
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
