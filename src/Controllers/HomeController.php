<?php

namespace PixelTrack\Controllers;

use PixelTrack\DataTransferObjects\TrackTransfer;
use PixelTrack\Repository\TrackRepository;
use PixelTrack\Repository\UserRepository;
use PixelTrack\Service\Config;
use PixelTrack\Service\Twig;
use PixelTrack\Service\Utility;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class HomeController
{
    public function __construct(
        private readonly Config $configService,
        private readonly UserRepository $userRepository,
        private readonly TrackRepository $trackRepository,
        private readonly Twig $twig,
        private readonly Utility $utility,
    ) {
    }

    public function index(Request $request): RedirectResponse
    {
        $cookies = $request->cookies;

        $userKey = $cookies->get('userKey');

        if (!$userKey || !$this->userRepository->userExists($userKey)) {
            return new RedirectResponse(
                '/send-magic-link'
            );
        }

        return new RedirectResponse(
            '/profile'
        );
    }

    public function profile(Request $request, Session $session): Response
    {
        $csrf = $this->utility->generateCsrfToken();
        $session->set('_csrf', $csrf);
        $cookies = $request->cookies;

        $userKey = $cookies->get('userKey');

        if (!$userKey || !$this->userRepository->userExists($userKey)) {
            $flashes = $session->getFlashBag();
            $flashes->add('danger', 'Profile does not exists. Please request a new magic link');
            return new RedirectResponse(
                '/send-magic-link'
            );
        }

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

    public function deleteTrack(string $userKey, Request $request, Session $session): Response
    {
        $csrfFormToken = $session->get('_csrf');
        $csrfToken = $request->request->get('_csrf');
        $trackId = $request->request->get('track_id');
        $flashes = $session->getFlashBag();

        if (!hash_equals($csrfFormToken, $csrfToken)) {
            $flashes = $session->getFlashBag();
            $flashes->add(
                'danger',
                'Invalid token'
            );

            return new RedirectResponse('/profile/');
        }
        $userTransfer = $this->userRepository->getUserByKey($userKey);

        if ($this->trackRepository->isTrackFromUser($trackId, $userTransfer->getId())) {
            $trackTransfer = $this->trackRepository->getTrackById($trackId);
            $this->deleteTrackFile($trackTransfer);
            $this->trackRepository->deleteTrack($trackId);
        }

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
