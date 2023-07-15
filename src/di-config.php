<?php

use PixelTrack\Cache\Cache;
use PixelTrack\RateLimiter\RateLimiter;
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
    }
];
