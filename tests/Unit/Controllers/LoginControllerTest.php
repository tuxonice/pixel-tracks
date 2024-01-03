<?php

namespace Unit\Controllers;

use PHPUnit\Framework\TestCase;
use PixelTrack\Controllers\LoginController;
use PixelTrack\DataTransfers\DataTransferObjects\UserTransfer;
use PixelTrack\Repository\UserRepository;
use PixelTrack\Service\Config;
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
        $configMock = $this->createMock(Config::class);

        $configMock->expects(self::once())
            ->method('getLoginToleranceTime')
            ->willReturn(5);

        $userRepositoryMock->expects(self::once())
            ->method('findUserByLoginKey')
            ->with('test-login-key', 5)
            ->willReturn(null);

        $sessionMock->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $flashBagMock->expects(self::once())
            ->method('add')
            ->with('danger', 'Invalid or expired magic link. Please request a new magic link');

        $loginController = new LoginController($userRepositoryMock, $configMock);

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
        $configMock = $this->createMock(Config::class);

        $configMock->expects(self::once())
            ->method('getLoginToleranceTime')
            ->willReturn(5);

        $userTransfer = new UserTransfer();
        $userTransfer
            ->setKey('test-user-key')
            ->setEmail('user@example.com');

        $userRepositoryMock->expects(self::once())
            ->method('findUserByLoginKey')
            ->with('test-login-key', 5)
            ->willReturn($userTransfer);

        $userRepositoryMock->expects(self::once())
            ->method('resetLoginKey')
            ->with('user@example.com');

        $sessionMock->expects(self::never())
            ->method('getFlashBag');

        $loginController = new LoginController($userRepositoryMock, $configMock);

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
