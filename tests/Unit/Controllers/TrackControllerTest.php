<?php

namespace Unit\Controllers;

use DateTime;
use DG\BypassFinals;
use PHPUnit\Framework\TestCase;
use PixelTrack\Controllers\TrackController;
use PixelTrack\DataTransfers\DataTransferObjects\TrackTransfer;
use PixelTrack\DataTransfers\DataTransferObjects\UserTransfer;
use PixelTrack\Repository\TrackRepository;
use PixelTrack\Repository\UserRepository;
use PixelTrack\Service\Config;
use PixelTrack\Service\GateKeeper;
use PixelTrack\Service\Twig;
use PixelTrack\Service\Utility;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Twig\Environment;
use Twig\TemplateWrapper;

class TrackControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BypassFinals::enable();
    }

    public function testCanSeeTrackInfo(): void
    {
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $utilityMock = $this->createMock(Utility::class);
        $sessionMock = $this->createMock(Session::class);
        $configMock = $this->createMock(Config::class);
        $gateKeeperMock = $this->createMock(GateKeeper::class);

        $utilityMock->expects(self::once())
            ->method('generateCsrfToken')
            ->willReturn('test-csrf-token');

        $sessionMock->expects(self::once())
            ->method('set')
            ->with('_csrf', 'test-csrf-token');

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('userKey')
            ->willReturn('test-user-key');

        $trackRepositoryMock->expects(self::once())
            ->method('getTrackByKey')
            ->with('test-track-key')
            ->willReturn(TrackTransfer::fromArray([
                'name' => 'test-track-name',
                'key' => 'test-track-key',
                'totalPoints' => 1000,
                'distance' => 20000,
                'elevation' => 400,
                'createdAt' => new DateTime('2023-12-31T19:42:17+00:00'),

            ]));

        $gateKeeperMock->expects(self::once())
            ->method('gate')
            ->with('test-user-key')
            ->willReturn(true);

        /** @phpstan-ignore-next-line */
        $templateWrapperMock = $this->createMock(TemplateWrapper::class);
        $templateWrapperMock->expects(self::once())
            ->method('render')
            ->with([
                'trackName' => 'test-track-name',
                'trackKey' => 'test-track-key',
                'points' => 1000,
                'distance' => 20000.0,
                'elevation' => 400.0,
                'createdAt' => '2023-12-31T19:42:17+00:00',
                'csrf' => 'test-csrf-token',
                'showLogout' => true,
            ])
            ->willReturn('track-html-template-content');

        $environmentMock = $this->createMock(Environment::class);
        $environmentMock->expects(self::once())
            ->method('load')
            ->with('Default/track.twig')
            ->willReturn($templateWrapperMock);

        $twigMock->expects(self::once())
            ->method('getTwig')
            ->willReturn($environmentMock);

        $trackController = new TrackController(
            $trackRepositoryMock,
            $twigMock,
            $configMock,
            $userRepositoryMock,
            $utilityMock,
            $gateKeeperMock,
        );

        $this->assertEquals(
            new Response('track-html-template-content'),
            $trackController->index($sessionMock, 'test-track-key')
        );
    }

    public function testCanNotSeeTrackInfoWhenNotLoggedIn(): void
    {
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $utilityMock = $this->createMock(Utility::class);
        $sessionMock = $this->createMock(Session::class);
        $configMock = $this->createMock(Config::class);
        $flashBagMock = $this->createMock(FlashBagInterface::class);
        $gateKeeperMock = $this->createMock(GateKeeper::class);

        $utilityMock->expects(self::once())
            ->method('generateCsrfToken')
            ->willReturn('test-csrf-token');

        $sessionMock->expects(self::once())
            ->method('set')
            ->with('_csrf', 'test-csrf-token');

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('userKey')
            ->willReturn('test-user-key');

        $trackRepositoryMock->expects(self::never())
            ->method('getTrackByKey');

        $gateKeeperMock->expects(self::once())
            ->method('gate')
            ->with('test-user-key')
            ->willReturn(false);

        $trackController = new TrackController(
            $trackRepositoryMock,
            $twigMock,
            $configMock,
            $userRepositoryMock,
            $utilityMock,
            $gateKeeperMock,
        );

        $this->assertEquals(
            new RedirectResponse('/send-magic-link'),
            $trackController->index($sessionMock, 'test-track-key')
        );
    }

    public function testCanNotDeleteTrackWhenNotLoggedIn(): void
    {
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $utilityMock = $this->createMock(Utility::class);
        $sessionMock = $this->createMock(Session::class);
        $configMock = $this->createMock(Config::class);
        $flashBagMock = $this->createMock(FlashBagInterface::class);
        $gateKeeperMock = $this->createMock(GateKeeper::class);

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('userKey')
            ->willReturn(null);

        $gateKeeperMock->expects(self::once())
            ->method('gate')
            ->with(null)
            ->willReturn(false);

        $request = new Request();
        $request->request->set('_csrf', 'test-csrf-token');
        $request->request->set('track_key', 'test-track-key');

        $trackController = new TrackController(
            $trackRepositoryMock,
            $twigMock,
            $configMock,
            $userRepositoryMock,
            $utilityMock,
            $gateKeeperMock,
        );

        $this->assertEquals(
            new RedirectResponse('/send-magic-link'),
            $trackController->deleteTrack($request, $sessionMock)
        );
    }

    public function testCanNotDeleteTrackWithInvalidCsrfToken(): void
    {
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $utilityMock = $this->createMock(Utility::class);
        $sessionMock = $this->createMock(Session::class);
        $configMock = $this->createMock(Config::class);
        $flashBagMock = $this->createMock(FlashBagInterface::class);
        $gateKeeperMock = $this->createMock(GateKeeper::class);

        $sessionMock->expects(self::exactly(2))
            ->method('get')
            ->willReturnMap([
                ['userKey', null, 'test-user-key'],
                ['_csrf', null, 'test-csrf-token-invalid'],
            ]);

        $trackRepositoryMock->expects(self::never())
            ->method('isTrackFromUser');

        $userRepositoryMock->expects(self::never())
            ->method('getUserByKey');

        $sessionMock->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $flashBagMock->expects(self::once())
            ->method('add')
            ->with('danger', 'Invalid token');

        $gateKeeperMock->expects(self::once())
            ->method('gate')
            ->with('test-user-key')
            ->willReturn(true);

        $request = new Request();
        $request->request->set('_csrf', 'test-csrf-token');
        $request->request->set('track_key', 'test-track-key');

        $homeController = new TrackController(
            $trackRepositoryMock,
            $twigMock,
            $configMock,
            $userRepositoryMock,
            $utilityMock,
            $gateKeeperMock,
        );

        $this->assertEquals(
            new RedirectResponse('/profile/'),
            $homeController->deleteTrack($request, $sessionMock)
        );
    }

    public function testCanNotDeleteTrackWhenTrackDoesNotExist(): void
    {
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $utilityMock = $this->createMock(Utility::class);
        $sessionMock = $this->createMock(Session::class);
        $configMock = $this->createMock(Config::class);
        $flashBagMock = $this->createMock(FlashBagInterface::class);
        $gateKeeperMock = $this->createMock(GateKeeper::class);

        $sessionMock->expects(self::exactly(2))
            ->method('get')
            ->willReturnMap([
                ['userKey', null, 'test-user-key'],
                ['_csrf', null, 'test-csrf-token'],
            ]);

        $trackRepositoryMock->expects(self::once())
            ->method('isTrackFromUser')
            ->willReturn(false);

        $userRepositoryMock->expects(self::once())
            ->method('getUserByKey')
            ->with('test-user-key')
            ->willReturn((new UserTransfer())->setId(1));

        $sessionMock->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $flashBagMock->expects(self::once())
            ->method('add')
            ->with('danger', 'Track does not exist!');

        $gateKeeperMock->expects(self::once())
            ->method('gate')
            ->with('test-user-key')
            ->willReturn(true);

        $request = new Request();
        $request->request->set('_csrf', 'test-csrf-token');
        $request->request->set('track_key', 'test-track-key');

        $homeController = new TrackController(
            $trackRepositoryMock,
            $twigMock,
            $configMock,
            $userRepositoryMock,
            $utilityMock,
            $gateKeeperMock,
        );

        $this->assertEquals(
            new RedirectResponse('/profile/'),
            $homeController->deleteTrack($request, $sessionMock)
        );
    }

    public function testDeleteTrack(): void
    {
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $utilityMock = $this->createMock(Utility::class);
        $sessionMock = $this->createMock(Session::class);
        $flashBagMock = $this->createMock(FlashBagInterface::class);
        $configMock = $this->createMock(Config::class);
        $gateKeeperMock = $this->createMock(GateKeeper::class);

        $sessionMock->expects(self::exactly(2))
            ->method('get')
            ->willReturnMap([
                ['userKey', null, 'test-user-key'],
                ['_csrf', null, 'test-csrf-token'],
            ]);

        $trackRepositoryMock->expects(self::once())
            ->method('isTrackFromUser')
            ->with('test-track-key', 1)
            ->willReturn(true);

        $trackTransfer = new TrackTransfer();
        $trackTransfer->setKey('track-key');
        $trackTransfer->setUserid(1);
        $trackTransfer->setFilename('track.gpx');

        $trackRepositoryMock->expects(self::once())
            ->method('getTrackByKey')
            ->with('test-track-key')
            ->willReturn($trackTransfer);

        $trackRepositoryMock->expects(self::once())
            ->method('deleteTrack')
            ->with('test-track-key');

        $userTransfer = new UserTransfer();
        $userTransfer->setKey('test-user-key');
        $userTransfer->setId(1);

        $userRepositoryMock->expects(self::once())
            ->method('getUserByKey')
            ->with('test-user-key')
            ->willReturn($userTransfer);


        $sessionMock->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $flashBagMock->expects(self::once())
            ->method('add')
            ->with('success', 'Track deleted');

        $gateKeeperMock->expects(self::once())
            ->method('gate')
            ->with('test-user-key')
            ->willReturn(true);

        $request = new Request();
        $request->request->set('_csrf', 'test-csrf-token');
        $request->request->set('track_key', 'test-track-key');

        $trackController = new TrackController(
            $trackRepositoryMock,
            $twigMock,
            $configMock,
            $userRepositoryMock,
            $utilityMock,
            $gateKeeperMock,
        );

        $this->assertEquals(
            new RedirectResponse('/profile/'),
            $trackController->deleteTrack($request, $sessionMock)
        );
    }
}
