<?php

namespace PixelTrack\Controllers;

use PixelTrack\Repository\TrackRepository;
use PixelTrack\Repository\UserRepository;
use PixelTrack\Service\Config;
use PixelTrack\Service\FileUploaderService;
use PixelTrack\Service\Utility;
use PixelTrack\Validator\XmlValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class UploadController
{
    private const VALID_FILE_TYPES = ['application/gpx+xml'];

    public function __construct(
        private readonly XmlValidator $xmlValidator,
        private readonly Config $configService,
        private readonly UserRepository $userRepository,
        private readonly TrackRepository $trackRepository,
        private readonly FileUploaderService $fileUploaderService,
        private readonly Utility $utility
    ) {
    }

    public function uploadTrack(string $userKey, Request $request, Session $session): Response
    {
        $csrfFormToken = $session->get('_csrf');
        $csrfToken = $request->request->get('_csrf');
        $flashes = $session->getFlashBag();

        if (!hash_equals($csrfFormToken, $csrfToken)) {
            $flashes->add(
                'danger',
                'Invalid token'
            );

            return new RedirectResponse('/profile/');
        }

        /** @var UploadedFile $file */
        $file = $request->files->get('trackFile');
        $trackName = htmlentities($request->request->get('trackName'));


        if (!$this->isValidFileType($file)) {
            $flashes->add('danger', 'GPX file has an invalid format');

            return new RedirectResponse(
                '/profile/'
            );
        }

        $userTransfer = $this->userRepository->getUserByKey($userKey);

        $targetFileName = $this->utility->generateRandomFileName($file);
        if (!$this->fileUploaderService->uploadFile($userTransfer, $file, $targetFileName)) {
            $flashes->add('danger', 'Unable to upload the file');

            return new RedirectResponse(
                '/profile/'
            );
        }

        if (!$this->trackRepository->insertTrack($userKey, $trackName, $targetFileName)) {
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
        $clientMimeType = $file->getClientMimeType();

        return in_array($clientMimeType, self::VALID_FILE_TYPES, true) &&
            $this->xmlValidator->isValid(
                $file->getContent(),
                file_get_contents($this->configService->getSchemaPath() . '/gpx.xsd')
            );
    }
}
