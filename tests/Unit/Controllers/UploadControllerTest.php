<?php

namespace Unit\Controllers;

use PHPUnit\Framework\TestCase;
use PixelTrack\Controllers\UploadController;
use PixelTrack\DataTransfers\DataTransferObjects\UserTransfer;
use PixelTrack\Repository\TrackRepository;
use PixelTrack\Repository\UserRepository;
use PixelTrack\Service\Config;
use PixelTrack\Service\FileUploaderService;
use PixelTrack\Service\Utility;
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
        $fileUploaderServiceMock = $this->createMock(FileUploaderService::class);
        $utilityMock = $this->createMock(Utility::class);
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
            $trackRepositoryMock,
            $fileUploaderServiceMock,
            $utilityMock
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
        $fileUploaderServiceMock = $this->createMock(FileUploaderService::class);
        $utilityMock = $this->createMock(Utility::class);
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
            $trackRepositoryMock,
            $fileUploaderServiceMock,
            $utilityMock
        );

        $this->assertEquals(
            new RedirectResponse('/profile/'),
            $uploadController->uploadTrack('user-key', $request, $sessionMock)
        );
    }

    public function testUploadTrack(): void
    {
        $xmlValidatorMock = $this->createMock(XmlValidator::class);
        $configMock = $this->createMock(Config::class);
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $fileUploaderServiceMock = $this->createMock(FileUploaderService::class);
        $utilityMock = $this->createMock(Utility::class);
        $sessionMock = $this->createMock(Session::class);
        $flashBagMock = $this->createMock(FlashBagInterface::class);

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('_csrf')
            ->willReturn('csrf-token');

        $flashBagMock->expects(self::once())
            ->method('add')
            ->with('success', 'New file uploaded');

        $sessionMock->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $xmlValidatorMock->expects(self::once())
            ->method('isValid')
            ->willReturn(true);

        $configMock->expects(self::once())
            ->method('getSchemaPath')
            ->willReturn(dirname(__DIR__, 3) . '/src/Schemas');


        $uploadedFile = new UploadedFile(
            dirname(__DIR__, 2) . '/Fixtures/sample.gpx',
            'track.gpx',
            'application/gpx+xml'
        );

        $utilityMock->expects(self::once())
            ->method('generateRandomFileName')
            ->with($uploadedFile)
            ->willReturn('track-file-name.gpx');

        $trackRepositoryMock->expects(self::once())
            ->method('insertTrack')
            ->with('user-key', 'track-name', 'track-file-name.gpx')
            ->willReturn(true);

        $userTransfer = (new UserTransfer())
            ->setId(1)
            ->setKey('user-key')
            ->setEmail('user@example.com');

        $fileUploaderServiceMock->expects(self::once())
            ->method('uploadFile')
            ->with($userTransfer, $uploadedFile, 'track-file-name.gpx')
            ->willReturn(true);

        $userRepositoryMock->expects(self::once())
            ->method('getUserByKey')
            ->with('user-key')
            ->willReturn($userTransfer);

        $request = new Request();
        $request->request->set('_csrf', 'csrf-token');
        $request->request->set('trackName', 'track-name');
        $request->files->set('trackFile', $uploadedFile);

        $uploadController = new UploadController(
            $xmlValidatorMock,
            $configMock,
            $userRepositoryMock,
            $trackRepositoryMock,
            $fileUploaderServiceMock,
            $utilityMock
        );

        $this->assertEquals(
            new RedirectResponse('/profile/'),
            $uploadController->uploadTrack('user-key', $request, $sessionMock)
        );
    }
}
