<?php

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use PixelTrack\Cache\Cache;
use PixelTrack\Mail\CarobMailer;
use PixelTrack\Mail\LogMailer;
use PixelTrack\Mail\MailProviderInterface;
use PixelTrack\Mail\SmtpMailer;
use PixelTrack\Middleware\AuthenticationMiddleware;
use PixelTrack\Middleware\CsrfMiddleware;
use PixelTrack\Middleware\ExceptionHandlerMiddleware;
use PixelTrack\RateLimiter\RateLimiter;
use PixelTrack\Service\Config;
use PixelTrack\Service\GpxValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

return [
    Request::class => function () {
        return Request::createFromGlobals();
    },
    Session::class => function () {
        $storage = new NativeSessionStorage(
            [
                'cookie_secure' => true,
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true
            ],
            new NativeFileSessionHandler()
        );
        $session = new Session($storage);
        if (!$session->isStarted()) {
            $session->start();
        }
        return $session;
    },

    // Register middleware components
    ExceptionHandlerMiddleware::class => function () {
        return new ExceptionHandlerMiddleware();
    },

    AuthenticationMiddleware::class => function () {
        return new AuthenticationMiddleware();
    },

    CsrfMiddleware::class => function () {
        return new CsrfMiddleware();
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
            'log' => new LogMailer(),
            default => throw new Exception('No mail provider'),
        };
    },
    Logger::class => function () {
        $logger = new Logger('TRACKS');
        $logger->pushHandler(
            new StreamHandler(Config::getLogsFolder() . '/error.log', Level::Debug)
        );

        return $logger;
    },

    GpxValidator::class => function () {
        return new GpxValidator();
    }
];
