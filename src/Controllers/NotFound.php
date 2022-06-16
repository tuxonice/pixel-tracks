<?php

namespace PixelTrack\Controllers;

use PixelTrack\Service\Twig;
use Symfony\Component\HttpFoundation\Response;

class NotFound
{
    public function __construct(
        private readonly Twig $twig,
    ) {
    }

    public function index(): Response
    {
        $template = $this->twig->getTwig()->load('Default/not-found.twig');
        $view = $template->render([]);

        return new Response(
            $view,
            Response::HTTP_NOT_FOUND
        );
    }
}
