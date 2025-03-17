<?php

namespace PixelTrack\Controllers;

use PixelTrack\Pagination\PaginatorQuery;
use PixelTrack\Service\Twig;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class HomeController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly PaginatorQuery $paginatorQuery,
    ) {
    }

    public function index(): RedirectResponse
    {
        return new RedirectResponse(
            '/profile'
        );
    }

    public function profile(Session $session, int $page = 1): Response
    {
        $userKey = $session->get('userKey');
        $paginatedTrackTransfer = $this->paginatorQuery->getTracksFromUser($userKey, $page, $_ENV['PAGINATION_IPP']);
        $template = $this->twig->getTwig()->load('Default/home.twig');
        $view = $template->render([
            'paginatedTracks' => $paginatedTrackTransfer,
            'userKey' => $userKey,
            'flashes' => $session->getFlashBag()->all(),
            '_token' => $session->get('_csrf_token'),
            'showLogout' => true,
        ]);

        return new Response(
            $view,
            Response::HTTP_OK
        );
    }
}
