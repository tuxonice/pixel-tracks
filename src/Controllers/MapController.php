<?php

namespace PixelTrack\Controllers;

use PixelTrack\Gps\GpsTrack;
use PixelTrack\Repository\TrackRepository;
use PixelTrack\Repository\UserRepository;
use PixelTrack\Service\GateKeeper;
use PixelTrack\Service\Twig;
use PixelTrack\Service\Utility;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class MapController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TrackRepository $trackRepository,
        private readonly Twig $twig,
        private readonly Utility $utility,
        private readonly GpsTrack $gpsTrack,
        private readonly GateKeeper $gateKeeper,
    ) {
    }

    public function index(Session $session, string $trackKey): Response
    {
        $userKey = $session->get('userKey');
        if (!$this->gateKeeper->gate($userKey)) {
            return new RedirectResponse(
                '/send-magic-link'
            );
        }

        $trackTransfer = $this->trackRepository->getTrackByKey($trackKey);
        if ($trackTransfer === null) {
            $flashes = $session->getFlashBag();
            $flashes->add('danger', 'Track file does not exist');
            return new RedirectResponse(
                '/'
            );
        }

        $userTransfer = $this->userRepository->getUserByKey($userKey);
        $trackFileName = $this->utility->getTrackFileName($userTransfer->getId(), $trackTransfer->getFilename());
        if (!$trackFileName) {
            $flashes = $session->getFlashBag();
            $flashes->add('danger', 'Track file does not exist');
            return new RedirectResponse(
                '/profile/'
            );
        }

        $this->gpsTrack->process($trackFileName);
        $trackInfo = $this->gpsTrack->getInfo();
        $template = $this->twig->getTwig()->load('Default/map.twig');
        $view = $template->render(
            [
                'title' => $trackTransfer->getName(),
                'points' => $this->gpsTrack->getJsonPoints(),
                'info' => $trackInfo,
            ]
        );
        return new Response(
            $view,
            Response::HTTP_OK
        );
    }
}
