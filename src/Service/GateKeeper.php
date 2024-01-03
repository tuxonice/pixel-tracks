<?php

namespace PixelTrack\Service;

use PixelTrack\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Session\Session;

class GateKeeper
{
    public function __construct(
        private Session $session,
        private UserRepository $userRepository,
    ) {
    }

    public function gate(?string $userKey): bool
    {
        if (!$userKey || !$this->userRepository->userExists($userKey)) {
            $flashes = $this->session->getFlashBag();
            $flashes->add('danger', 'Profile does not exists. Please request a new magic link');

            return false;
        }

        return true;
    }
}
