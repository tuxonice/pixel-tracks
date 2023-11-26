<?php

namespace Unit\Controllers;

use DateTime;
use PHPUnit\Framework\TestCase;
use PixelTrack\Controllers\UploadController;
use PixelTrack\DataTransfers\DataTransferObjects\TrackTransfer;
use PixelTrack\DataTransfers\DataTransferObjects\UserTransfer;
use PixelTrack\Gps\GpsTrack;
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
        $gpsTrackMock = $this->createMock(GpsTrack::class);

        $sessionMock->expects(self::exactly(2))
            ->method('get')
            ->willReturnMap([
                ['userKey', null, 'test-user-key'],
                ['_csrf', null, 'csrf-form-token'],
            ]);

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
            $utilityMock,
            $gpsTrackMock,
        );

        $this->assertEquals(
            new RedirectResponse('/profile/'),
            $uploadController->uploadTrack($request, $sessionMock)
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
        $gpsTrackMock = $this->createMock(GpsTrack::class);

        $sessionMock->expects(self::exactly(2))
            ->method('get')
            ->willReturnMap([
                ['userKey', null, 'test-user-key'],
                ['_csrf', null, 'csrf-token'],
            ]);

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
            $utilityMock,
            $gpsTrackMock,
        );

        $this->assertEquals(
            new RedirectResponse('/profile/'),
            $uploadController->uploadTrack($request, $sessionMock)
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
        $gpsTrackMock = $this->createMock(GpsTrack::class);

        $gpsTrackMock->expects(self::once())
            ->method('process')
            ->with('/track-file-name.gpx');

        $gpsTrackMock->expects(self::once())
            ->method('getInfo')
            ->willReturn([
                'points' => 1000,
                'totalHeight' => 400.0,
                'totalDistance' => 20000,
            ]);

        $sessionMock->expects(self::exactly(2))
            ->method('get')
            ->willReturnMap([
                ['userKey', null, 'user-key'],
                ['_csrf', null, 'csrf-token'],
            ]);

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

        $utilityMock->expects(self::once())
            ->method('generateTrackKey')
            ->willReturn('test-track-key');

        $utilityMock->expects(self::once())
            ->method('currentDateTime')
            ->willReturn(new DateTime('2023-12-31T13:03:14'));

        $trackRepositoryMock->expects(self::once())
            ->method('insertTrack')
            ->with(TrackTransfer::fromArray([
                'totalPoints' => 1000,
                'elevation' => 400.0,
                'distance' => 20000.0,
                'userId' => 1,
                'name' => 'track-name',
                'key' => 'test-track-key',
                'filename' => 'track-file-name.gpx',
                'createdAt' => new DateTime('2023-12-31T13:03:14')
            ]))
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
        $request->cookies->set('userKey', 'user-key');

        $uploadController = new UploadController(
            $xmlValidatorMock,
            $configMock,
            $userRepositoryMock,
            $trackRepositoryMock,
            $fileUploaderServiceMock,
            $utilityMock,
            $gpsTrackMock,
        );

        $this->assertEquals(
            new RedirectResponse('/profile/'),
            $uploadController->uploadTrack($request, $sessionMock)
        );
    }
}
