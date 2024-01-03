<?php

namespace PixelTrack\Controllers;

use PixelTrack\Repository\UserRepository;
use PixelTrack\Service\Config;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class LoginController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly Config $config,
    ) {
    }

    public function login(Session $session, string $loginKey): Response
    {
        $loginToleranceTime = $this->config->getLoginToleranceTime();
        $userTransfer = $this->userRepository->findUserByLoginKey($loginKey, $loginToleranceTime);
        if (!$userTransfer) {
            $flashes = $session->getFlashBag();
            $flashes->add('danger', 'Invalid or expired magic link. Please request a new magic link');
            return new RedirectResponse(
                '/send-magic-link'
            );
        }

        $session->set('userKey', $userTransfer->getKey());
        $this->userRepository->resetLoginKey($userTransfer->getEmail());

        return new RedirectResponse('/profile/', 302);
    }
}
