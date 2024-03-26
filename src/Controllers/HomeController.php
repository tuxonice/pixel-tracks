<?php

namespace PixelTrack\Controllers;

use PixelTrack\Pagination\PaginatorQuery;
use PixelTrack\Service\GateKeeper;
use PixelTrack\Service\Twig;
use PixelTrack\Service\Utility;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class HomeController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly Utility $utility,
        private readonly GateKeeper $gateKeeper,
        private readonly PaginatorQuery $paginatorQuery,
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

    public function profile(Session $session, int $page = 1): Response
    {
        $userKey = $session->get('userKey');
        if (!$this->gateKeeper->gate($userKey)) {
            return new RedirectResponse(
                '/send-magic-link'
            );
        }
        $csrf = $this->utility->generateCsrfToken();
        $session->set('_csrf', $csrf);

        $paginatedTrackTransfer = $this->paginatorQuery->getTracksFromUser($userKey, $page, $_ENV['PAGINATION_IPP']);
        $template = $this->twig->getTwig()->load('Default/home.twig');
        $view = $template->render([
            'paginatedTracks' => $paginatedTrackTransfer,
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
