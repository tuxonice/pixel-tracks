<?php

namespace Unit\Controllers;

use PHPUnit\Framework\TestCase;
use PixelTrack\Controllers\LogoutController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LogoutControllerTest extends TestCase
{
    public function testLogout(): void
    {
        $logoutController = new LogoutController();
        $cookie = new Cookie('userKey', '', '2000-01-01');

        $expectedResponse = new RedirectResponse(
            '/send-magic-link',
            302,
            [$cookie]
        );
        $expectedResponse->headers->setCookie($cookie);

        $this->assertEquals(
            $expectedResponse,
            $logoutController->index()
        );
    }
}
