<?php

namespace PixelTrack\Middleware;

use PixelTrack\App;
use PixelTrack\Service\GateKeeper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AuthenticationMiddleware implements MiddlewareInterface
{
    private const PUBLIC_ROUTES = [
        '/login',
        '/send-magic-link',
    ];

    private GateKeeper $gateKeeper;

    public function __construct()
    {
        $app = App::getInstance();
        $this->gateKeeper = $app->getContainer()->get(GateKeeper::class);
    }

    public function handle(Request $request, callable|\Closure $next): Response
    {
        $path = $request->getPathInfo();
        if ($this->isPublicRoute($path)) {
            return $next($request);
        }

        if (!$this->gateKeeper->isAuthenticated()) {
            return new RedirectResponse('/send-magic-link');
        }

        return $next($request);
    }

    private function isPublicRoute(string $path): bool
    {
        foreach (self::PUBLIC_ROUTES as $route) {
            if (str_starts_with($path, $route)) {
                return true;
            }
        }

        return false;
    }
}
