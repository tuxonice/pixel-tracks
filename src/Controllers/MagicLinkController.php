<?php

namespace PixelTrack\Controllers;

use PixelTrack\App;
use PixelTrack\Cache\Cache;
use PixelTrack\RateLimiter\RateLimiter;
use PixelTrack\Repository\UserRepository;
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
        private readonly UserRepository $userRepository,
        private readonly Cache $cache,
    ) {
        $this->app = App::getInstance();
    }

    public function requestMagicLink(): Response
    {
        $csrf = sha1(uniqid('', true));
        $session = $this->app->getSession();
        $session->set('_csrf', $csrf);

        $template = $this->twig->getTwig()->load('Default/magic-link.twig');
        $view = $template->render([
            'flashes' => $this->app->getSession()->getFlashBag()->all(),
            'csrf' => $csrf,
        ]);

        return new Response(
            $view,
            Response::HTTP_OK
        );
    }

    public function sendMagicLink(): Response
    {
        $request = $this->app->getRequest();
        $rateLimiter = new RateLimiter([
            'refillPeriod' => $_ENV['RATE_LIMITER_REFILL_PERIOD'],
            'maxCapacity' => $_ENV['RATE_LIMITER_MAX_CAPACITY'],
            'prefix' => 'magic-link-'
        ], $this->cache);

        $ipAddress = $request->getClientIp();
        if (!$rateLimiter->check($ipAddress)) {
            return new Response(
                '<h1>429 Too many requests</h1>',
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        $session = $this->app->getSession();
        $csrfFormToken = $session->get('_csrf');
        $csrfToken = $request->request->get('_csrf');

        if (!hash_equals($csrfFormToken, $csrfToken)) {
            $flashes = $this->app->getSession()->getFlashBag();
            $flashes->add(
                'danger',
                'Invalid token'
            );

            return new RedirectResponse('/send-magic-link');
        }

        $email = $request->request->get('email');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $flashes = $this->app->getSession()->getFlashBag();
            $flashes->add(
                'danger',
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

        return new RedirectResponse('/send-magic-link');
    }

    /**
     * @param string $email
     *
     * @return string
     */
    private function generateUserKey(string $email): string
    {
        $userKey = $this->userRepository->findUserByEmail($email);

        if ($userKey === null) {
            return $this->userRepository->createUserByEmail($email);
        }

        return $this->userRepository->regenerateUserKey($userKey);
    }
}
