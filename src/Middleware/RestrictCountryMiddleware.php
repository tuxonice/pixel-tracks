<?php

namespace PixelTrack\Middleware;

use PixelTrack\Service\Config;
use PixelTrack\Service\IpApiService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictCountryMiddleware implements MiddlewareInterface
{
    private IpApiService $ipApiService;

    public function __construct()
    {
        $this->ipApiService = new IpApiService();
    }


    public function handle(Request $request, \Closure $next): Response
    {
        $allowCountryCode = Config::getAllowCountryCode();

        if ($allowCountryCode) {
            $countryCode = $this->ipApiService->getCountryByIp($request->getClientIp()) ?: '';
            if (strtolower($countryCode) !== $allowCountryCode) {
                return new Response('Forbidden', Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }
}
