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
    ) {
    }

    public function index(string $userKey, string $trackKey, Session $session): Response
    {
        $trackTransfer = $this->trackRepository->getTrackFilename($userKey, $trackKey);
        $userTransfer = $this->userRepository->getUserByKey($userKey);

        if ($trackTransfer === null) {
            return new RedirectResponse(
                '/'
            );
        }

        $userProfileFolder = sprintf('profile-%03d', $userTransfer->getId());
        $trackFileName = $this->config->getDataPath() . '/' . $userProfileFolder . '/' . $trackTransfer->getFilename();
        if (!file_exists($trackFileName)) {
            $flashes = $session->getFlashBag();
            $flashes->add('danger', 'Track file does not exist');
            return new RedirectResponse(
                '/profile/'
            );
        }
        $track = new GpsTrack($trackFileName);
        $trackInfo = $track->getInfo();
        $template = $this->twig->getTwig()->load('Default/map.twig');
        $view = $template->render(
            [
                'title' => $trackTransfer->getName(),
                'points' => $track->getJsonPoints(),
                'info' => $trackInfo,
            ]
        );
        return new Response(
            $view,
            Response::HTTP_OK
        );
    }
}
