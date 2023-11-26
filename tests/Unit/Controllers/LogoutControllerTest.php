<?php

namespace Unit\Controllers;

use PHPUnit\Framework\TestCase;
use PixelTrack\Controllers\LogoutController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;

class LogoutControllerTest extends TestCase
{
    public function testLogout(): void
    {
        $sessionMock = $this->createMock(Session::class);
        $sessionMock->expects(self::once())
            ->method('clear');

        $logoutController = new LogoutController();
        $expectedResponse = new RedirectResponse(
            '/send-magic-link',
            302
        );

        $this->assertEquals(
            $expectedResponse,
            $logoutController->index($sessionMock)
        );
    }
}
