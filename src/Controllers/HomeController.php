<?php

namespace PixelTrack\Controllers;

use PixelTrack\App;
use PixelTrack\DataTransferObjects\TrackTransfer;
use PixelTrack\Repository\DatabaseRepository;
use PixelTrack\Service\Config;
use PixelTrack\Service\Twig;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;

class HomeController
{
    private App $app;

    public function __construct(
        private readonly Config $configService,
        private readonly DatabaseRepository $databaseRepository,
        private readonly Twig $twig,
    ) {
        $this->app = App::getInstance();
    }

    public function index(string $userKey): RedirectResponse
    {
        if (!$userKey || !$this->databaseRepository->userExists($userKey)) {
            $flashes = $this->app->getSession()->getFlashBag();
            $flashes->add('danger', 'Profile does not exists. Please request a new magic link');
            return new RedirectResponse(
                '/send-magic-link'
            );
        }

        $cookie = new Cookie('userKey', $userKey, '2037-01-01');
        $redirectResponse = new RedirectResponse('/profile/', 302, [$cookie]);
        $redirectResponse->headers->setCookie($cookie);

        return $redirectResponse;
    }

    public function profile(): Response
    {
        $csrf = sha1(uniqid('', true));
        $session = $this->app->getSession();
        $session->set('_csrf', $csrf);

        $request = $this->app->getRequest();
        $cookies = $request->cookies;

        $userKey = $cookies->get('userKey');

        if (!$userKey || !$this->databaseRepository->userExists($userKey)) {
            $flashes = $this->app->getSession()->getFlashBag();
            $flashes->add('danger', 'Profile does not exists. Please request a new magic link');
            return new RedirectResponse(
                '/send-magic-link'
            );
        }

        $tracks = $this->databaseRepository->getTracksFromUser($userKey);
        $template = $this->twig->getTwig()->load('Default/home.twig');
        $view = $template->render([
            'tracks' => $tracks,
            'userKey' => $userKey,
            'flashes' => $this->app->getSession()->getFlashBag()->all(),
            'csrf' => $csrf,
            'showLogout' => true,
        ]);

        return new Response(
            $view,
            Response::HTTP_OK
        );
    }

    public function deleteTrack(string $userKey): Response
    {
        $request = $this->app->getRequest();
        $session = $this->app->getSession();
        $csrfFormToken = $session->get('_csrf');
        $csrfToken = $request->request->get('_csrf');
        $trackId = $request->request->get('track_id');
        $flashes = $this->app->getSession()->getFlashBag();

        if (!hash_equals($csrfFormToken, $csrfToken)) {
            $flashes = $this->app->getSession()->getFlashBag();
            $flashes->add(
                'danger',
                'Invalid token'
            );

            return new RedirectResponse('/profile/');
        }
        $userTransfer = $this->databaseRepository->getUserByKey($userKey);

        if ($this->databaseRepository->isTrackFromUser($trackId, $userTransfer->getId())) {
            $trackTransfer = $this->databaseRepository->getTrackById($trackId);
            $this->deleteTrackFile($trackTransfer);
            $this->databaseRepository->deleteTrack($trackId);
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
