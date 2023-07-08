<?php

namespace PixelTrack\Controllers;

use PixelTrack\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class LoginController
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {
    }

    public function login(string $loginKey, Session $session): Response
    {
        $userTransfer = $this->userRepository->findUserByLoginKey($loginKey);
        if (!$userTransfer) {
            $flashes = $session->getFlashBag();
            $flashes->add('danger', 'Profile does not exists. Please request a new magic link');
            return new RedirectResponse(
                '/send-magic-link'
            );
        }

        $cookie = new Cookie('userKey', $userTransfer->getKey(), '2037-01-01');
        $redirectResponse = new RedirectResponse('/profile/', 302, [$cookie]);
        $redirectResponse->headers->setCookie($cookie);

        return $redirectResponse;
    }
}
