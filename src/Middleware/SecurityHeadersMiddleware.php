<?php

namespace PixelTrack\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable|\Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; "
            . "script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdn.jsdelivr.net https://unpkg.com; "
            . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://unpkg.com; "
            . "font-src 'self' https://fonts.gstatic.com; "
            . "img-src 'self' data: https://*.tile.openstreetmap.org; "
            . "connect-src 'self'; "
            . "frame-ancestors 'none'"
        );

        return $response;
    }
}
