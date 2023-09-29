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

    public function testRedirectToMagicLinkWhenUserNotFound()
    {
        $configMock = $this->createMock(Config::class);
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $utilityMock = $this->createMock(Utility::class);

        $userRepositoryMock->expects(self::never())
            ->method('userExists');

        $homeController = new HomeController(
            $configMock,
            $userRepositoryMock,
            $trackRepositoryMock,
            $twigMock,
            $utilityMock,
        );

        $this->assertEquals(
            new RedirectResponse('/send-magic-link'),
            $homeController->index(new Request())
        );
    }

    public function testRedirectToProfileWhenUserFound()
    {
        $configMock = $this->createMock(Config::class);
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $utilityMock = $this->createMock(Utility::class);

        $userRepositoryMock->expects(self::once())
            ->method('userExists')
            ->with('test-user-key')
            ->willReturn(true);

        $request = new Request();
        $request->cookies->set('userKey', 'test-user-key');

        $homeController = new HomeController(
            $configMock,
            $userRepositoryMock,
            $trackRepositoryMock,
            $twigMock,
            $utilityMock,
        );

        $this->assertEquals(
            new RedirectResponse('/profile'),
            $homeController->index($request)
        );
    }

    public function testProfileRedirectToMagicLinkWhenUserNotFound()
    {
        $configMock = $this->createMock(Config::class);
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $utilityMock = $this->createMock(Utility::class);

        $userRepositoryMock->expects(self::never())
            ->method('userExists');

        $request = new Request();
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
            $configMock,
            $userRepositoryMock,
            $trackRepositoryMock,
            $twigMock,
            $utilityMock,
        );

        $this->assertEquals(
            new RedirectResponse('/send-magic-link'),
            $homeController->profile($request, $sessionMock)
        );
    }

    public function testProfileShowTrackListWhenUserExists()
    {
        $configMock = $this->createMock(Config::class);
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

        $request = new Request();
        $request->cookies->set('userKey', 'test-user-key');

        $sessionMock = $this->createMock(Session::class);
        $flashBagMock = $this->createMock(FlashBagInterface::class);

        $sessionMock->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $sessionMock->expects(self::once())
            ->method('set');

        $flashBagMock->expects(self::once())
            ->method('all');

        $homeController = new HomeController(
            $configMock,
            $userRepositoryMock,
            $trackRepositoryMock,
            $twigMock,
            $utilityMock,
        );

        $homeController->profile($request, $sessionMock);
    }

    public function testDeleteTrackWithInvalidCsrfToken(): void
    {
        $configMock = $this->createMock(Config::class);
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $utilityMock = $this->createMock(Utility::class);
        $sessionMock = $this->createMock(Session::class);
        $flashBagMock = $this->createMock(FlashBagInterface::class);

        $trackRepositoryMock->expects(self::never())
            ->method('isTrackFromUser');

        $userRepositoryMock->expects(self::never())
            ->method('getUserByKey');

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('_csrf')
            ->willReturn('test-csrf-token-invalid');

        $sessionMock->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $flashBagMock->expects(self::once())
            ->method('add')
            ->with('danger', 'Invalid token');

        $request = new Request();
        $request->request->set('_csrf', 'test-csrf-token');
        $request->request->set('track_id', 1);

        $homeController = new HomeController(
            $configMock,
            $userRepositoryMock,
            $trackRepositoryMock,
            $twigMock,
            $utilityMock,
        );

        $this->assertEquals(
            new RedirectResponse('/profile/'),
            $homeController->deleteTrack('test-user-key', $request, $sessionMock)
        );
    }

    public function testDeleteTrack(): void
    {
        $configMock = $this->createMock(Config::class);
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);
        $utilityMock = $this->createMock(Utility::class);
        $sessionMock = $this->createMock(Session::class);
        $flashBagMock = $this->createMock(FlashBagInterface::class);

        $trackRepositoryMock->expects(self::once())
            ->method('isTrackFromUser')
            ->with(1, 1)
            ->willReturn(true);

        $trackTransfer = new TrackTransfer();
        $trackTransfer->setKey('track-key');
        $trackTransfer->setUserid(1);
        $trackTransfer->setFilename('track.gpx');

        $trackRepositoryMock->expects(self::once())
            ->method('getTrackById')
            ->with(1)
            ->willReturn($trackTransfer);

        $trackRepositoryMock->expects(self::once())
            ->method('deleteTrack')
            ->with(1);

        $userTransfer = new UserTransfer();
        $userTransfer->setKey('test-user-key');
        $userTransfer->setId(1);

        $userRepositoryMock->expects(self::once())
            ->method('getUserByKey')
            ->with('test-user-key')
            ->willReturn($userTransfer);

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('_csrf')
            ->willReturn('test-csrf-token');

        $sessionMock->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $flashBagMock->expects(self::once())
            ->method('add')
            ->with('success', 'Track deleted');

        $request = new Request();
        $request->request->set('_csrf', 'test-csrf-token');
        $request->request->set('track_id', 1);

        $homeController = new HomeController(
            $configMock,
            $userRepositoryMock,
            $trackRepositoryMock,
            $twigMock,
            $utilityMock,
        );

        $this->assertEquals(
            new RedirectResponse('/profile/'),
            $homeController->deleteTrack('test-user-key', $request, $sessionMock)
        );
    }
}
