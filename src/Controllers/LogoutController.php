<?php

namespace PixelTrack\Controllers;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class LogoutController
{
    public function index(Session $session): Response
    {
        $session->clear();

        return new RedirectResponse('/send-magic-link', Response::HTTP_FOUND);
    }
}
