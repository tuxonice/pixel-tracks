<?php

namespace Unit\Controllers;

use DG\BypassFinals;
use PHPUnit\Framework\TestCase;
use PixelTrack\Controllers\HomeController;
use PixelTrack\DataTransfers\DataTransferObjects\TrackTransfer;
use PixelTrack\Repository\TrackRepository;
use PixelTrack\Service\GateKeeper;
use PixelTrack\Service\Twig;
use PixelTrack\Service\Utility;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $utilityMock = $this->createMock(Utility::class);
        $sessionMock = $this->createMock(Session::class);
        $gateKeeperMock = $this->createMock(GateKeeper::class);

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('userKey')
            ->willReturn(null);

        $gateKeeperMock->expects(self::once())
            ->method('gate')
            ->with(null)
            ->willReturn(false);

        $homeController = new HomeController(
            $trackRepositoryMock,
            $twigMock,
            $utilityMock,
            $gateKeeperMock,
        );

        $this->assertEquals(
            new RedirectResponse('/send-magic-link'),
            $homeController->index($sessionMock)
        );
    }

    public function testRedirectToProfileWhenUserFound(): void
    {
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $utilityMock = $this->createMock(Utility::class);
        $sessionMock = $this->createMock(Session::class);
        $gateKeeperMock = $this->createMock(GateKeeper::class);

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('userKey')
            ->willReturn('test-user-key');

        $gateKeeperMock->expects(self::once())
            ->method('gate')
            ->with('test-user-key')
            ->willReturn(true);

        $homeController = new HomeController(
            $trackRepositoryMock,
            $twigMock,
            $utilityMock,
            $gateKeeperMock
        );

        $this->assertEquals(
            new RedirectResponse('/profile'),
            $homeController->index($sessionMock)
        );
    }

    public function testProfileRedirectToMagicLinkWhenUserNotFound(): void
    {
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $utilityMock = $this->createMock(Utility::class);
        $gateKeeperMock = $this->createMock(GateKeeper::class);
        $sessionMock = $this->createMock(Session::class);

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('userKey')
            ->willReturn(null);

        $gateKeeperMock->expects(self::once())
            ->method('gate')
            ->with(null)
            ->willReturn(false);

        $homeController = new HomeController(
            $trackRepositoryMock,
            $twigMock,
            $utilityMock,
            $gateKeeperMock,
        );

        $this->assertEquals(
            new RedirectResponse('/send-magic-link'),
            $homeController->profile($sessionMock)
        );
    }

    public function testProfileShowTrackListWhenUserExists(): void
    {
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $utilityMock = $this->createMock(Utility::class);
        $gateKeeperMock = $this->createMock(GateKeeper::class);

        $trackRepositoryMock->expects(self::once())
            ->method('getTracksFromUser')
            ->with('test-user-key')
            ->willReturn(
                [(new TrackTransfer())]
            );

        $utilityMock->expects(self::once())
            ->method('generateCsrfToken')
            ->willReturn('test-csrf-token');

        $gateKeeperMock->expects(self::once())
            ->method('gate')
            ->with('test-user-key')
            ->willReturn(true);

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
            $trackRepositoryMock,
            $twigMock,
            $utilityMock,
            $gateKeeperMock,
        );

        $homeController->profile($sessionMock);
    }
}
