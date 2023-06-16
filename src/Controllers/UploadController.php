<?php

namespace PixelTrack\Controllers;

use PixelTrack\App;
use PixelTrack\DataTransferObjects\UserTransfer;
use PixelTrack\Repository\DatabaseRepository;
use PixelTrack\Service\Config;
use PixelTrack\Validator\XmlValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class UploadController
{
    private const VALID_FILE_TYPES = ['application/gpx+xml'];

    private App $app;

    public function __construct(
        private readonly XmlValidator $xmlValidator,
        private readonly Config $configService,
        private readonly DatabaseRepository $databaseRepository,
    ) {
        $this->app = App::getInstance();
    }

    public function uploadTrack(string $userKey): Response
    {
        $request = $this->app->getRequest();
        $session = $this->app->getSession();
        $csrfFormToken = $session->get('_csrf');
        $csrfToken = $request->request->get('_csrf');
        $flashes = $this->app->getSession()->getFlashBag();

        if (!hash_equals($csrfFormToken, $csrfToken)) {
            $flashes = $this->app->getSession()->getFlashBag();
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

        $userTransfer = $this->databaseRepository->getUserByKey($userKey);

        $targetFileName = uniqid() . '.' . $file->getClientOriginalExtension();
        if (!$this->uploadFile($userTransfer, $file, $targetFileName)) {
            $flashes->add('danger', 'Unable to upload the file');

            return new RedirectResponse(
                '/profile/'
            );
        }

        if (!$this->updateDatabase($userKey, $trackName, $targetFileName)) {
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

    private function uploadFile(UserTransfer $userTransfer, UploadedFile $file, string $targetFileName): bool
    {
        $userFolder = $this->configService->getDataPath() . sprintf('/profile-%03d', $userTransfer->getId());
        $splFileInfo = $file->getFileInfo();
        if (!file_exists($userFolder)) {
            if (!mkdir($userFolder)) {
                return false;
            };
        }


        if (!move_uploaded_file($splFileInfo->getRealPath(), $userFolder . '/' . $targetFileName)) {
            return false;
        }

        return true;
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

    private function updateDatabase(string $userKey, string $trackName, string $targetFileName): bool
    {
        return $this->databaseRepository->insertTrack($userKey, $trackName, $targetFileName);
    }
}