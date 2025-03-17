<?php

namespace PixelTrack\Middleware;

use PixelTrack\App;
use PixelTrack\Exception\CsrfTokenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class CsrfMiddleware implements MiddlewareInterface
{
    private const TOKEN_KEY = '_csrf_token';

    private const POST_TOKEN_KEY = '_token';

    private Session $session;

    public function __construct()
    {
        $app = App::getInstance();
        $this->session = $app->getContainer()->get(Session::class);
    }

    public function handle(Request $request, callable|\Closure $next): Response
    {
        $x = $request->getRequestUri();
        if ($this->shouldVerifyToken($request)) {
            if (!$this->isValidToken($request)) {
                throw new CsrfTokenException('Invalid CSRF token');
            }
        }

        $this->session->set(self::TOKEN_KEY, bin2hex(random_bytes(32)));
        return $next($request);
    }

    private function shouldVerifyToken(Request $request): bool
    {
        return in_array($request->getMethod(), ['POST', 'PUT', 'DELETE', 'PATCH']);
    }

    private function isValidToken(Request $request): bool
    {
        $token = $request->request->get(self::POST_TOKEN_KEY);
        return $token && hash_equals($this->session->get(self::TOKEN_KEY, ''), $token);
    }
}
