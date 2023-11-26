<?php

namespace PixelTrack\Controllers;

use PixelTrack\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class LoginController
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {
    }

    public function login(Session $session, string $loginKey): Response
    {
        $userTransfer = $this->userRepository->findUserByLoginKey($loginKey);
        if (!$userTransfer) {
            $flashes = $session->getFlashBag();
            $flashes->add('danger', 'Profile does not exists. Please request a new magic link');
            return new RedirectResponse(
                '/send-magic-link'
            );
        }

        $session->set('userKey', $userTransfer->getKey());
        $this->userRepository->resetLoginKey($userTransfer->getEmail());

        return new RedirectResponse('/profile/', 302);
    }
}
