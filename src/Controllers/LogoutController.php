<?php

namespace PixelTrack\Controllers;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class LogoutController
{
    public function index(): Response
    {
        $cookie = new Cookie('userKey', '', '2000-01-01');
        $redirectResponse = new RedirectResponse('/send-magic-link', 302, [$cookie]);
        $redirectResponse->headers->setCookie($cookie);

        return $redirectResponse;
    }
}
