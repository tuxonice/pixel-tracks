<?php

namespace PixelTrack\Controllers;

use PixelTrack\Gps\GpsTrack;
use PixelTrack\Repository\TrackRepository;
use PixelTrack\Repository\UserRepository;
use PixelTrack\Service\Config;
use PixelTrack\Service\Twig;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class MapController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TrackRepository $trackRepository,
        private readonly Twig $twig,
        private readonly Config $config,
        private readonly GpsTrack $gpsTrack,
    ) {
    }

    public function index(Session $session, string $trackKey): Response
    {
        $trackTransfer = $this->trackRepository->getTrackFilename($trackKey);
        if ($trackTransfer === null) {
            $flashes = $session->getFlashBag();
            $flashes->add('danger', 'Track file does not exist');
            return new RedirectResponse(
                '/'
            );
        }

        $userKey = $session->get('userKey');
        $userTransfer = $this->userRepository->getUserByKey($userKey);

        $userProfileFolder = sprintf('profile-%03d', $userTransfer->getId());
        $trackFileName = $this->config->getDataPath() . '/' . $userProfileFolder . '/' . $trackTransfer->getFilename();
        if (!file_exists($trackFileName)) {
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
