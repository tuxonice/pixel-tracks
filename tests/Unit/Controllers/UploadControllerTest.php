<?php

namespace Unit\Controllers;

use PHPUnit\Framework\TestCase;
use PixelTrack\Controllers\UploadController;
use PixelTrack\Repository\TrackRepository;
use PixelTrack\Repository\UserRepository;
use PixelTrack\Service\Config;
use PixelTrack\Validator\XmlValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class UploadControllerTest extends TestCase
{
    public function testUploadTrackWithInvalidCsrfToken(): void
    {
        $xmlValidatorMock = $this->createMock(XmlValidator::class);
        $configMock = $this->createMock(Config::class);
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $sessionMock = $this->createMock(Session::class);
        $flashBagMock = $this->createMock(FlashBagInterface::class);

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('_csrf')
            ->willReturn('csrf-form-token');

        $flashBagMock->expects(self::once())
            ->method('add')
            ->with('danger', 'Invalid token');

        $sessionMock->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $request = new Request();
        $request->request->set('_csrf', 'csrf-token');

        $uploadController = new UploadController(
            $xmlValidatorMock,
            $configMock,
            $userRepositoryMock,
            $trackRepositoryMock
        );

        $this->assertEquals(
            new RedirectResponse('/profile/'),
            $uploadController->uploadTrack('user-key', $request, $sessionMock)
        );
    }

    public function testUploadTrackWithInvalidFileType(): void
    {
        $xmlValidatorMock = $this->createMock(XmlValidator::class);
        $configMock = $this->createMock(Config::class);
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $sessionMock = $this->createMock(Session::class);
        $flashBagMock = $this->createMock(FlashBagInterface::class);

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('_csrf')
            ->willReturn('csrf-token');

        $flashBagMock->expects(self::once())
            ->method('add')
            ->with('danger', 'GPX file has an invalid format');

        $sessionMock->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $uploadedFile = new UploadedFile(
            dirname(__DIR__, 2) . '/Fixtures/sample.gpx',
            'track.gpx',
            'application/xml'
        );

        $request = new Request();
        $request->request->set('_csrf', 'csrf-token');
        $request->files->set('trackFile', $uploadedFile);

        $uploadController = new UploadController(
            $xmlValidatorMock,
            $configMock,
            $userRepositoryMock,
            $trackRepositoryMock
        );

        $this->assertEquals(
            new RedirectResponse('/profile/'),
            $uploadController->uploadTrack('user-key', $request, $sessionMock)
        );
    }
}
