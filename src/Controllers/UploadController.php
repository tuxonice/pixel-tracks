<?php

namespace PixelTrack\Controllers;

use PixelTrack\DataTransfers\DataTransferObjects\TrackTransfer;
use PixelTrack\Exception\GpxValidationException;
use PixelTrack\Gps\GpsTrack;
use PixelTrack\Repository\TrackRepository;
use PixelTrack\Repository\UserRepository;
use PixelTrack\Service\Config;
use PixelTrack\Service\FileUploaderService;
use PixelTrack\Service\GpxValidator;
use PixelTrack\Service\Utility;
use PixelTrack\Validator\XmlValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class UploadController
{
    public function __construct(
        private readonly XmlValidator $xmlValidator,
        private readonly GpxValidator $gpxValidator,
        private readonly Config $configService,
        private readonly UserRepository $userRepository,
        private readonly TrackRepository $trackRepository,
        private readonly FileUploaderService $fileUploaderService,
        private readonly Utility $utility,
        private readonly GpsTrack $gpsTrack,
    ) {
    }

    public function uploadTrack(Request $request, Session $session): Response
    {
        $userKey = $session->get('userKey');
        $flashes = $session->getFlashBag();

        /** @var UploadedFile $file */
        $file = $request->files->get('trackFile');
        if (!$file) {
            $flashes->add('danger', 'No file was uploaded');
            return new RedirectResponse('/profile/');
        }

        $trackName = trim(htmlspecialchars($request->request->get('trackName', '')));
        if (empty($trackName)) {
            $flashes->add('danger', 'Track name is required');
            return new RedirectResponse('/profile/');
        }

        try {
            $this->isValidFileType($file);
        } catch (GpxValidationException $e) {
            $flashes->add('danger', $e->getMessage());
            return new RedirectResponse('/profile/');
        }

        $userTransfer = $this->userRepository->getUserByKey($userKey);

        $targetFileName = $this->utility->generateRandomFileName($file);
        if (!$this->fileUploaderService->uploadFile($userTransfer, $file, $targetFileName)) {
            $flashes->add('danger', 'Unable to upload the file');

            return new RedirectResponse(
                '/profile/'
            );
        }

        $trackFileName = $this->configService->getUserDataPath($userTransfer->getId()) . '/' . $targetFileName;
        $this->gpsTrack->process($trackFileName);
        $trackInfo = $this->gpsTrack->getInfo();

        $trackTransfer = new TrackTransfer();
        $trackTransfer
            ->setName($trackName)
            ->setFilename($targetFileName)
            ->setCreatedAt($this->utility->currentDateTime())
            ->setKey($this->utility->generateTrackKey())
            ->setTotalPoints($trackInfo['points'])
            ->setElevation($trackInfo['totalHeight'])
            ->setDistance($trackInfo['totalDistance'])
            ->setUserId($userTransfer->getId());

        if (!$this->trackRepository->insertTrack($trackTransfer)) {
            $flashes->add('danger', 'Unable to save track');

            return new RedirectResponse(
                '/profile/'
            );
        }

        $flashes->add('success', 'New file uploaded');

        return new RedirectResponse(
            '/profile/'
        );
    }

    private function isValidFileType(UploadedFile $file): bool
    {
        try {
            // First validate against XSD schema
            $isValidXml = $this->xmlValidator->isValid(
                $file->getContent(),
                file_get_contents($this->configService->getSchemaPath() . '/gpx.xsd')
            );

            if (!$isValidXml) {
                throw new GpxValidationException('Invalid GPX file format');
            }

            // Then perform comprehensive GPX validation
            $this->gpxValidator->validate($file);

            return true;
        } catch (GpxValidationException $e) {
            throw $e; // Re-throw to be caught by the ExceptionHandlerMiddleware
        } catch (\Throwable $e) {
            throw new GpxValidationException('Error validating GPX file: ' . $e->getMessage());
        }
    }
}
