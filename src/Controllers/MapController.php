<?php

namespace PixelTrack\Controllers;

use PixelTrack\DataTransferObjects\TrackTransfer;
use PixelTrack\GpsTrack;
use PixelTrack\Repository\DatabaseRepository;
use PixelTrack\Service\Twig;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class MapController
{
    public function __construct(
        private readonly DatabaseRepository $databaseRepository,
        private readonly Twig $twig,
    ) {
    }

    public function index(string $userKey, string $trackKey): Response
    {
        $trackTransfer = $this->databaseRepository->getTrackFilename($userKey, $trackKey);

        if ($trackTransfer === null) {
            return new RedirectResponse(
                '/'
            );
        }

        $track = new GpsTrack($userKey . '/' . $trackTransfer->getFilename());
        $template = $this->twig->getTwig()->load('Default/map.twig');
        $view = $template->render(
            [
                'title' => $trackTransfer->getName(),
                'points' => $track->getJsonPoints()
            ]
        );
        return new Response(
            $view,
            Response::HTTP_OK
        );
    }
}
