<?php

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use PixelTrack\Cache\Cache;
use PixelTrack\Mail\CarobMailer;
use PixelTrack\Mail\MailProviderInterface;
use PixelTrack\Mail\SmtpMailer;
use PixelTrack\RateLimiter\RateLimiter;
use PixelTrack\Service\Config;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

return [
    Request::class => function () {
        return Request::createFromGlobals();
    },
    Session::class => function () {
        $session = new Session();
        $session->start();

        return $session;
    },
    RateLimiter::class => function () {
        return new RateLimiter([
            'refillPeriod' => $_ENV['RATE_LIMITER_REFILL_PERIOD'],
            'maxCapacity' => $_ENV['RATE_LIMITER_MAX_CAPACITY'],
            'prefix' => 'magic-link-'
        ], new Cache());
    },
    MailProviderInterface::class => function () {
        return match ($_ENV['MAIL_PROVIDER']) {
            'smtp' => new SmtpMailer(),
            'carob-mailer' => new CarobMailer(),
            default => throw new Exception('No mail provider'),
        };
    },
    Logger::class => function () {
        $logger = new Logger('TRACKS');
        $logger->pushHandler(
            new StreamHandler(Config::getLogsFolder() . '/error.log', Level::Debug)
        );

        return $logger;
    }
];
