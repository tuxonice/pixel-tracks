<?php

namespace PixelTrack\Controllers;

use PixelTrack\DataTransfers\DataTransferObjects\MailMessageTransfer;
use PixelTrack\DataTransfers\DataTransferObjects\MailRecipientTransfer;
use PixelTrack\Mail\MailProviderInterface;
use PixelTrack\RateLimiter\RateLimiter;
use PixelTrack\Repository\UserRepository;
use PixelTrack\Service\Config;
use PixelTrack\Service\Twig;
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
        private MailProviderInterface $mailProvider,
    ) {
    }

    public function requestMagicLink(Session $session): Response
    {
        $template = $this->twig->getTwig()->load('Default/magic-link.twig');
        $view = $template->render([
            'flashes' => $session->getFlashBag()->all(),
            '_token' => $session->get('_csrf_token'),
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
        $this->userRepository->regenerateUserKey($email);


        $templateHtml = $this->twig->getTwig()->load('Default/Mail/magic-link-html.twig');
        $viewHtml = $templateHtml->render(['link' => $this->configService->getBaseUrl() . '/login/' . $loginKey]);

        $templateText = $this->twig->getTwig()->load('Default/Mail/magic-link-text.twig');
        $viewText = $templateText->render(['link' => $this->configService->getBaseUrl() . '/login/' . $loginKey]);

        $mailMessageTransfer = new MailMessageTransfer();
        $mailMessageTransfer->setFrom(
            (new mailRecipientTransfer())
                ->setName('Pixel Tracks')
                ->setEmail($_ENV['EMAIL_FROM'])
        )
            ->setTo(
                (new mailRecipientTransfer())
                    ->setName($email)
                    ->setEmail($email)
            )
            ->setSubject('Here is your magic link')
            ->setHtmlBody($viewHtml)
            ->setTextBody($viewText);

        $this->mailProvider->send($mailMessageTransfer);

        $flashes = $session->getFlashBag();
        $flashes->add(
            'success',
            'Please verify your mailbox'
        );

        return new RedirectResponse('/send-magic-link');
    }
}
