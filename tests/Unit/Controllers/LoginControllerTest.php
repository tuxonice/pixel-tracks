<?php

namespace Unit\Controllers;

use PHPUnit\Framework\TestCase;
use PixelTrack\Controllers\LoginController;
use PixelTrack\DataTransferObjects\UserTransfer;
use PixelTrack\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class LoginControllerTest extends TestCase
{
    public function testLoginWithInvalidLoginKey(): void
    {
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $sessionMock = $this->createMock(Session::class);
        $flashBagMock = $this->createMock(FlashBagInterface::class);

        $userRepositoryMock->expects(self::once())
            ->method('findUserByLoginKey')
            ->with('test-login-key')
            ->willReturn(null);

        $sessionMock->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $flashBagMock->expects(self::once())
            ->method('add')
            ->with('danger', 'Profile does not exists. Please request a new magic link');

        $loginController = new LoginController($userRepositoryMock);

        $this->assertEquals(
            new RedirectResponse(
                '/send-magic-link'
            ),
            $loginController->login('test-login-key', $sessionMock)
        );
    }

    public function testLoginWithValidLoginKey(): void
    {
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $sessionMock = $this->createMock(Session::class);

        $userTransfer = new UserTransfer();
        $userTransfer->setKey('test-user-key');

        $userRepositoryMock->expects(self::once())
            ->method('findUserByLoginKey')
            ->with('test-login-key')
            ->willReturn($userTransfer);

        $sessionMock->expects(self::never())
            ->method('getFlashBag');

        $loginController = new LoginController($userRepositoryMock);
        $cookie = new Cookie('userKey', 'test-user-key', '2037-01-01');

        $expectedResponse = new RedirectResponse(
            '/profile/',
            302,
            [$cookie]
        );
        $expectedResponse->headers->setCookie($cookie);

        $this->assertEquals(
            $expectedResponse,
            $loginController->login('test-login-key', $sessionMock)
        );
    }
}
