<?php

namespace PixelTrack\Controllers;

use PixelTrack\DataTransfers\DataTransferObjects\TrackTransfer;
use PixelTrack\Repository\TrackRepository;
use PixelTrack\Repository\UserRepository;
use PixelTrack\Service\Config;
use PixelTrack\Service\Twig;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class TrackController
{
    public function __construct(
        private readonly TrackRepository $trackRepository,
        private readonly Twig $twig,
        private readonly Config $configService,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function index(Session $session, string $trackKey): Response
    {
        $trackTransfer = $this->trackRepository->getTrackByKey($trackKey);

        $template = $this->twig->getTwig()->load('Default/track.twig');
        $view = $template->render([
            'trackName' => $trackTransfer->getName(),
            'trackKey' => $trackTransfer->getKey(),
            'points' => $trackTransfer->getTotalPoints(),
            'distance' => $trackTransfer->getDistance(),
            'elevation' => $trackTransfer->getElevation(),
            'createdAt' => $trackTransfer->getCreatedAt()->format('c'),
            '_token' => $session->get('_csrf_token'),
            'showLogout' => true,
        ]);

        return new Response(
            $view,
            Response::HTTP_OK
        );
    }

    public function deleteTrack(Request $request, Session $session): Response
    {
        $userKey = $session->get('userKey');
        $trackKey = $request->request->get('track_key');
        $flashes = $session->getFlashBag();
        $userTransfer = $this->userRepository->getUserByKey($userKey);

        if (!$this->trackRepository->isTrackFromUser($trackKey, $userTransfer->getId())) {
            $flashes->add(
                'danger',
                'Track does not exist!'
            );

            return new RedirectResponse('/profile/');
        }

        $trackTransfer = $this->trackRepository->getTrackByKey($trackKey);
        $this->deleteTrackFile($trackTransfer);
        $this->trackRepository->deleteTrack($trackKey);
        $flashes->add('success', 'Track deleted');

        return new RedirectResponse(
            '/profile/'
        );
    }

    private function deleteTrackFile(TrackTransfer $trackTransfer): void
    {
        $filename = sprintf(
            "%s/profile-%03d/%s",
            $this->configService->getDataPath(),
            $trackTransfer->getUserId(),
            $trackTransfer->getFilename()
        );

        if (file_exists($filename)) {
            unlink($filename);
        }
    }
}
