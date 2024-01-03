<?php

namespace PixelTrack\Controllers;

use PixelTrack\Repository\TrackRepository;
use PixelTrack\Service\GateKeeper;
use PixelTrack\Service\Twig;
use PixelTrack\Service\Utility;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class HomeController
{
    public function __construct(
        private readonly TrackRepository $trackRepository,
        private readonly Twig $twig,
        private readonly Utility $utility,
        private readonly GateKeeper $gateKeeper,
    ) {
    }

    public function index(Session $session): RedirectResponse
    {
        $userKey = $session->get('userKey');
        if (!$this->gateKeeper->gate($userKey)) {
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
        $userKey = $session->get('userKey');
        if (!$this->gateKeeper->gate($userKey)) {
            return new RedirectResponse(
                '/send-magic-link'
            );
        }
        $csrf = $this->utility->generateCsrfToken();
        $session->set('_csrf', $csrf);

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
