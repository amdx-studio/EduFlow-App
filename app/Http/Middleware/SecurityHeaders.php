<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * UT-04, TC-061: header keamanan HTTP.
 * CSP dan HSTS hanya di production: saat pengembangan Vite memuat skrip dari server lain dan menyisipkan gaya inline.
 */
class SecurityHeaders
{
    public const CSP = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

    public const HSTS = 'max-age=15552000';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (app()->isProduction()) {
            $response->headers->set('Content-Security-Policy', self::CSP);

            if ($request->isSecure()) {
                $response->headers->set('Strict-Transport-Security', self::HSTS);
            }
        }

        return $response;
    }
}
