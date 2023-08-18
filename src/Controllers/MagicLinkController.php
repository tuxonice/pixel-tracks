<?php

namespace PixelTrack\Controllers;

use PixelTrack\RateLimiter\RateLimiter;
use PixelTrack\Repository\UserRepository;
use PixelTrack\Service\Config;
use PixelTrack\Service\MailConnectorService;
use PixelTrack\Service\Twig;
use PixelTrack\Service\Utility;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class MagicLinkController
{
    public function __construct(
        private Config $configService,
        private Twig $twig,
        private UserRepository $userRepository,
        private RateLimiter $rateLimiter,
        private Utility $utility,
        private MailConnectorService $mailConnectorService,
    ) {
    }

    public function requestMagicLink(Session $session): Response
    {
        $csrf = $this->utility->generateCsrfToken();
        $session->set('_csrf', $csrf);

        $template = $this->twig->getTwig()->load('Default/magic-link.twig');
        $view = $template->render([
            'flashes' => $session->getFlashBag()->all(),
            'csrf' => $csrf,
        ]);

        return new Response(
            $view,
            Response::HTTP_OK
        );
    }

    public function sendMagicLink(Request $request, Session $session): Response
    {
        $ipAddress = $request->getClientIp();
        if (!$this->rateLimiter->check($ipAddress)) {
            return new Response(
                '<h1>429 Too many requests</h1>',
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        $csrfFormToken = $session->get('_csrf');
        $csrfToken = $request->request->get('_csrf');

        if (!hash_equals($csrfFormToken, $csrfToken)) {
            $flashes = $session->getFlashBag();
            $flashes->add(
                'danger',
                'Invalid token'
            );

            return new RedirectResponse('/send-magic-link');
        }

        $email = $request->request->get('email');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $flashes = $session->getFlashBag();
            $flashes->add(
                'danger',
                'Invalid email'
            );

            return new RedirectResponse('/send-magic-link');
        }

        $userTransfer = $this->userRepository->findUserByEmail($email);
        if (!$userTransfer) {
            $this->userRepository->createUserByEmail($email);
        }
        $loginKey = $this->userRepository->regenerateLoginKey($email);


        $template = $this->twig->getTwig()->load('Default/Mail/magic-link.twig');
        $view = $template->render(['link' => $this->configService->getBaseUrl() . 'login/' . $loginKey]);

        $data = [
                'to' => $email,
                'subject' => 'Here is your magic link',
                'body' => $view
        ];

        $this->mailConnectorService->sendRequest($data);

        $flashes = $session->getFlashBag();
        $flashes->add(
            'success',
            'Please verify your mailbox'
        );

        return new RedirectResponse('/send-magic-link');
    }
}
