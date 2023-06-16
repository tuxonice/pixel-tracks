<?php

namespace PixelTrack\Controllers;

use PixelTrack\App;
use PixelTrack\Gps\GpsTrack;
use PixelTrack\Repository\DatabaseRepository;
use PixelTrack\Service\Config;
use PixelTrack\Service\Twig;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class MapController
{
    private App $app;

    public function __construct(
        private readonly DatabaseRepository $databaseRepository,
        private readonly Twig $twig,
        private readonly Config $config,
    ) {
        $this->app = App::getInstance();
    }

    public function index(string $userKey, string $trackKey): Response
    {
        $trackTransfer = $this->databaseRepository->getTrackFilename($userKey, $trackKey);
        $userTransfer = $this->databaseRepository->getUserByKey($userKey);

        if ($trackTransfer === null) {
            return new RedirectResponse(
                '/'
            );
        }

        $userProfileFolder = sprintf('profile-%03d', $userTransfer->getId());
        $trackFileName = $this->config->getDataPath() . '/' . $userProfileFolder . '/' . $trackTransfer->getFilename();
        if (!file_exists($trackFileName)) {
            $flashes = $this->app->getSession()->getFlashBag();
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
