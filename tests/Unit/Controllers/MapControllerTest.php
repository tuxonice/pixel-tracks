<?php

namespace Unit\Controllers;

use DG\BypassFinals;
use PixelTrack\Controllers\MapController;
use PHPUnit\Framework\TestCase;
use PixelTrack\DataTransfers\DataTransferObjects\TrackTransfer;
use PixelTrack\DataTransfers\DataTransferObjects\UserTransfer;
use PixelTrack\Gps\GpsTrack;
use PixelTrack\Repository\TrackRepository;
use PixelTrack\Repository\UserRepository;
use PixelTrack\Service\GateKeeper;
use PixelTrack\Service\Twig;
use PixelTrack\Service\Utility;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Twig\Environment;
use Twig\TemplateWrapper;

class MapControllerTest extends TestCase
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
        $sessionMock = $this->createMock(Session::class);
        $gateKeeperMock = $this->createMock(GateKeeper::class);
        $utilityMock = $this->createMock(Utility::class);
        $gpsTrackMock = $this->createMock(GpsTrack::class);

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('userKey')
            ->willReturn(null);

        $gateKeeperMock->expects(self::once())
            ->method('gate')
            ->with(null)
            ->willReturn(false);

        $mapController = new MapController(
            $userRepositoryMock,
            $trackRepositoryMock,
            $twigMock,
            $utilityMock,
            $gpsTrackMock,
            $gateKeeperMock,
        );

        $this->assertEquals(
            new RedirectResponse('/send-magic-link'),
            $mapController->index($sessionMock, 'track-key')
        );
    }

    public function testRedirectToHomeWhenTrackFileNameDoesNotExist(): void
    {
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $sessionMock = $this->createMock(Session::class);
        $gateKeeperMock = $this->createMock(GateKeeper::class);
        $utilityMock = $this->createMock(Utility::class);
        $gpsTrackMock = $this->createMock(GpsTrack::class);
        $flashBagMock = $this->createMock(FlashBagInterface::class);

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('userKey')
            ->willReturn('test-user-key');

        $gateKeeperMock->expects(self::once())
            ->method('gate')
            ->with('test-user-key')
            ->willReturn(true);

        $trackRepositoryMock->expects(self::once())
            ->method('getTrackByKey')
            ->with('test-track-key')
            ->willReturn(null);

        $flashBagMock->expects(self::once())
            ->method('add')
            ->with('danger', 'Track file does not exist');

        $sessionMock->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $mapController = new MapController(
            $userRepositoryMock,
            $trackRepositoryMock,
            $twigMock,
            $utilityMock,
            $gpsTrackMock,
            $gateKeeperMock,
        );

        $this->assertEquals(
            new RedirectResponse('/'),
            $mapController->index($sessionMock, 'test-track-key')
        );
    }

    public function testRedirectToProfileWhenTrackFileNameDoesNotExist(): void
    {
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $sessionMock = $this->createMock(Session::class);
        $gateKeeperMock = $this->createMock(GateKeeper::class);
        $utilityMock = $this->createMock(Utility::class);
        $gpsTrackMock = $this->createMock(GpsTrack::class);
        $flashBagMock = $this->createMock(FlashBagInterface::class);

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('userKey')
            ->willReturn('test-user-key');

        $gateKeeperMock->expects(self::once())
            ->method('gate')
            ->with('test-user-key')
            ->willReturn(true);

        $trackRepositoryMock->expects(self::once())
            ->method('getTrackByKey')
            ->with('test-track-key')
            ->willReturn(
                (new TrackTransfer())
                    ->setFilename('test-file-name.gpx')
                    ->setName('Test Track')
            );

        $userRepositoryMock->expects(self::once())
            ->method('getUserByKey')
            ->with('test-user-key')
            ->willReturn((new UserTransfer())->setId(1));

        $utilityMock->expects(self::once())
            ->method('getTrackFileName')
            ->with(1, 'test-file-name.gpx')
            ->willReturn(null);

        $gpsTrackMock->expects(self::never())
            ->method('process');

        $flashBagMock->expects(self::once())
            ->method('add')
            ->with('danger', 'Track file does not exist');

        $sessionMock->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $mapController = new MapController(
            $userRepositoryMock,
            $trackRepositoryMock,
            $twigMock,
            $utilityMock,
            $gpsTrackMock,
            $gateKeeperMock,
        );

        $this->assertEquals(
            new RedirectResponse('/profile/'),
            $mapController->index($sessionMock, 'test-track-key')
        );
    }

    public function testShowMap(): void
    {
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $sessionMock = $this->createMock(Session::class);
        $gateKeeperMock = $this->createMock(GateKeeper::class);
        $utilityMock = $this->createMock(Utility::class);
        $gpsTrackMock = $this->createMock(GpsTrack::class);

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('userKey')
            ->willReturn('test-user-key');

        $gateKeeperMock->expects(self::once())
            ->method('gate')
            ->with('test-user-key')
            ->willReturn(true);

        $trackRepositoryMock->expects(self::once())
            ->method('getTrackByKey')
            ->with('test-track-key')
            ->willReturn(
                (new TrackTransfer())
                    ->setFilename('test-file-name.gpx')
                    ->setName('Test Track')
            );

        $userRepositoryMock->expects(self::once())
            ->method('getUserByKey')
            ->with('test-user-key')
            ->willReturn((new UserTransfer())->setId(1));

        $utilityMock->expects(self::once())
            ->method('getTrackFileName')
            ->with(1, 'test-file-name.gpx')
            ->willReturn('/path/to/test-file-name.gpx');

        $gpsTrackMock->expects(self::once())
            ->method('process')
            ->with('/path/to/test-file-name.gpx');

        $gpsTrackMock->expects(self::once())
            ->method('getInfo')
            ->willReturn([
                'points' => 1000,
                'totalDistance' => '20000',
                'totalHeight' => '200',
            ]);

        $gpsTrackMock->expects(self::once())
            ->method('getJsonPoints')
            ->willReturn('');

        /** @phpstan-ignore-next-line */
        $templateWrapperMock = $this->createMock(TemplateWrapper::class);
        $templateWrapperMock->expects(self::once())
            ->method('render')
            ->with([
                'title' => 'Test Track',
                'points' => '',
                'info' => [
                    'points' => 1000,
                    'totalDistance' => '20000',
                    'totalHeight' => '200',
                ],
            ]);

        $environmentMock = $this->createMock(Environment::class);
        $environmentMock->expects(self::once())
            ->method('load')
            ->with('Default/map.twig')
            ->willReturn($templateWrapperMock);

        $twigMock->expects(self::once())
            ->method('getTwig')
            ->willReturn($environmentMock);

        $mapController = new MapController(
            $userRepositoryMock,
            $trackRepositoryMock,
            $twigMock,
            $utilityMock,
            $gpsTrackMock,
            $gateKeeperMock,
        );

        $this->assertEquals(
            new Response(''),
            $mapController->index($sessionMock, 'test-track-key')
        );
    }
}
