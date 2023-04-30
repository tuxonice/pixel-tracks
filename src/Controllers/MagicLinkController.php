<?php

namespace PixelTrack\Controllers;

use PixelTrack\App;
use PixelTrack\Repository\DatabaseRepository;
use PixelTrack\Service\Config;
use PixelTrack\Service\Mail;
use PixelTrack\Service\Twig;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class MagicLinkController
{
    private App $app;

    public function __construct(
        private readonly Mail $mail,
        private readonly Config $configService,
        private readonly Twig $twig,
        private readonly DatabaseRepository $databaseRepository,
    ) {
        $this->app = App::getInstance();
    }

    public function requestMagicLink(): Response
    {
        $template = $this->twig->getTwig()->load('Default/magic-link.twig');
        $view = $template->render([
            'flashes' => $this->app->getSession()->getFlashBag()->all()
        ]);

        return new Response(
            $view,
            Response::HTTP_OK
        );
    }


    public function sendMagicLink(): Response
    {
        $request = $this->app->getRequest();
        $email = $request->request->get('email');

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $flashes = $this->app->getSession()->getFlashBag();
            $flashes->add(
                'error',
                'Invalid email'
            );

            return new RedirectResponse('/send-magic-link');
        }

        $userKey = $this->generateUserKey($email);

        $template = $this->twig->getTwig()->load('Default/Mail/magic-link.twig');
        $view = $template->render(['link' => $this->configService->getBaseUrl() . 'profile/' . $userKey]);

        $mailData = [
            'from' => [
                'email' => $_ENV['EMAIL_FROM'],
                'name' => 'PixelTracks',
            ],
            'to' => [
                [
                    'email' => $email,
                    'name' => $email,
                ]
            ],
            'subject' => 'Here is your magic link',
            'body' => $view,
        ];


        $this->mail->send($mailData);

        $flashes = $this->app->getSession()->getFlashBag();
        $flashes->add(
            'success',
            'Please verify your mailbox'
        );

        $template = $this->twig->getTwig()->load('Default/magic-link.twig');
        $view = $template->render([
            'flashes' => $this->app->getSession()->getFlashBag()->all()
        ]);

        return new Response(
            $view,
            Response::HTTP_OK
        );
    }

    /**
     * @param string $email
     *
     * @return string
     */
    private function generateUserKey(string $email): string
    {
        $userKey = $this->databaseRepository->findUserByEmail($email);

        if ($userKey === null) {
            return $this->databaseRepository->createUserByEmail($email);
        }

        return $this->databaseRepository->regenerateUserKey($userKey);
    }
}
