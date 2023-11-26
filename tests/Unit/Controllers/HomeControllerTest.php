<?php

namespace Unit\Controllers;

use DG\BypassFinals;
use PHPUnit\Framework\TestCase;
use PixelTrack\Controllers\HomeController;
use PixelTrack\DataTransfers\DataTransferObjects\TrackTransfer;
use PixelTrack\DataTransfers\DataTransferObjects\UserTransfer;
use PixelTrack\Repository\TrackRepository;
use PixelTrack\Repository\UserRepository;
use PixelTrack\Service\Config;
use PixelTrack\Service\Twig;
use PixelTrack\Service\Utility;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Twig\Environment;
use Twig\TemplateWrapper;

class HomeControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BypassFinals::enable();
    }

    public function testRedirectToMagicLinkWhenUserNotFound(): void
    {
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $utilityMock = $this->createMock(Utility::class);
        $sessionMock = $this->createMock(Session::class);

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('userKey')
            ->willReturn(null);

        $userRepositoryMock->expects(self::never())
            ->method('userExists');

        $homeController = new HomeController(
            $userRepositoryMock,
            $trackRepositoryMock,
            $twigMock,
            $utilityMock,
        );

        $this->assertEquals(
            new RedirectResponse('/send-magic-link'),
            $homeController->index($sessionMock)
        );
    }

    public function testRedirectToProfileWhenUserFound(): void
    {
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $utilityMock = $this->createMock(Utility::class);
        $sessionMock = $this->createMock(Session::class);

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('userKey')
            ->willReturn('test-user-key');

        $userRepositoryMock->expects(self::once())
            ->method('userExists')
            ->with('test-user-key')
            ->willReturn(true);

        $homeController = new HomeController(
            $userRepositoryMock,
            $trackRepositoryMock,
            $twigMock,
            $utilityMock,
        );

        $this->assertEquals(
            new RedirectResponse('/profile'),
            $homeController->index($sessionMock)
        );
    }

    public function testProfileRedirectToMagicLinkWhenUserNotFound(): void
    {
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $utilityMock = $this->createMock(Utility::class);

        $userRepositoryMock->expects(self::never())
            ->method('userExists');

        $sessionMock = $this->createMock(Session::class);
        $flashBagMock = $this->createMock(FlashBagInterface::class);

        $sessionMock->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $sessionMock->expects(self::once())
            ->method('set');

        $flashBagMock->expects(self::once())
            ->method('add')
            ->with('danger', 'Profile does not exists. Please request a new magic link');

        $homeController = new HomeController(
            $userRepositoryMock,
            $trackRepositoryMock,
            $twigMock,
            $utilityMock,
        );

        $this->assertEquals(
            new RedirectResponse('/send-magic-link'),
            $homeController->profile($sessionMock)
        );
    }

    public function testProfileShowTrackListWhenUserExists(): void
    {
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $utilityMock = $this->createMock(Utility::class);

        $userRepositoryMock->expects(self::once())
            ->method('userExists')
            ->with('test-user-key')
            ->willReturn(true);

        $trackRepositoryMock->expects(self::once())
            ->method('getTracksFromUser')
            ->with('test-user-key')
            ->willReturn(
                [(new TrackTransfer())]
            );

        $utilityMock->expects(self::once())
            ->method('generateCsrfToken')
            ->willReturn('test-csrf-token');

        /** @phpstan-ignore-next-line */
        $templateWrapperMock = $this->createMock(TemplateWrapper::class);
        $templateWrapperMock->expects(self::once())
            ->method('render')
            ->with([
                'tracks' => [new TrackTransfer()],
                'userKey' => 'test-user-key',
                'flashes' => [],
                'csrf' => 'test-csrf-token',
                'showLogout' => true,
            ]);

        $environmentMock = $this->createMock(Environment::class);
        $environmentMock->expects(self::once())
            ->method('load')
            ->with('Default/home.twig')
            ->willReturn($templateWrapperMock);

        $twigMock->expects(self::once())
            ->method('getTwig')
            ->willReturn($environmentMock);

        $sessionMock = $this->createMock(Session::class);
        $flashBagMock = $this->createMock(FlashBagInterface::class);

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('userKey')
            ->willReturn('test-user-key');

        $sessionMock->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $sessionMock->expects(self::once())
            ->method('set');

        $flashBagMock->expects(self::once())
            ->method('all');

        $homeController = new HomeController(
            $userRepositoryMock,
            $trackRepositoryMock,
            $twigMock,
            $utilityMock,
        );

        $homeController->profile($sessionMock);
    }
}
