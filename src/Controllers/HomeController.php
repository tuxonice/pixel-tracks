<?php

namespace PixelTrack\Controllers;

use PixelTrack\Repository\TrackRepository;
use PixelTrack\Repository\UserRepository;
use PixelTrack\Service\Twig;
use PixelTrack\Service\Utility;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class HomeController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TrackRepository $trackRepository,
        private readonly Twig $twig,
        private readonly Utility $utility,
    ) {
    }

    public function index(Session $session): RedirectResponse
    {
        $userKey = $session->get('userKey');

        if (!$userKey || !$this->userRepository->userExists($userKey)) {
            return new RedirectResponse(
                '/send-magic-link'
            );
        }

        return new RedirectResponse(
            '/profile'
        );
    }

    public function profile(Session $session): Response
    {
        $csrf = $this->utility->generateCsrfToken();
        $session->set('_csrf', $csrf);
        $userKey = $session->get('userKey');

        if (!$userKey || !$this->userRepository->userExists($userKey)) {
            $flashes = $session->getFlashBag();
            $flashes->add('danger', 'Profile does not exists. Please request a new magic link');
            return new RedirectResponse(
                '/send-magic-link'
            );
        }

        $tracks = $this->trackRepository->getTracksFromUser($userKey);
        $template = $this->twig->getTwig()->load('Default/home.twig');
        $view = $template->render([
            'tracks' => $tracks,
            'userKey' => $userKey,
            'flashes' => $session->getFlashBag()->all(),
            'csrf' => $csrf,
            'showLogout' => true,
        ]);

        return new Response(
            $view,
            Response::HTTP_OK
        );
    }
}
