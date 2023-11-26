<?php

namespace Unit\Controllers;

use PHPUnit\Framework\TestCase;
use PixelTrack\Controllers\LoginController;
use PixelTrack\DataTransfers\DataTransferObjects\UserTransfer;
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
            $loginController->login($sessionMock, 'test-login-key')
        );
    }

    public function testLoginWithValidLoginKey(): void
    {
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $sessionMock = $this->createMock(Session::class);

        $userTransfer = new UserTransfer();
        $userTransfer
            ->setKey('test-user-key')
            ->setEmail('user@example.com');

        $userRepositoryMock->expects(self::once())
            ->method('findUserByLoginKey')
            ->with('test-login-key')
            ->willReturn($userTransfer);

        $userRepositoryMock->expects(self::once())
            ->method('resetLoginKey')
            ->with('user@example.com');

        $sessionMock->expects(self::never())
            ->method('getFlashBag');

        $loginController = new LoginController($userRepositoryMock);

        $expectedResponse = new RedirectResponse(
            '/profile/',
            302
        );

        $this->assertEquals(
            $expectedResponse,
            $loginController->login($sessionMock, 'test-login-key')
        );
    }
}
