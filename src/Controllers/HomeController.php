<?php

namespace PixelTrack\Controllers;

use PixelTrack\App;
use PixelTrack\Repository\DatabaseRepository;
use PixelTrack\Service\Config;
use PixelTrack\Service\Twig;
use PixelTrack\Validator\XmlValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class HomeController
{
    private const VALID_FILE_TYPES = ['application/gpx+xml'];

    private App $app;

    public function __construct(
        private readonly XmlValidator $xmlValidator,
        private readonly Config $configService,
        private readonly DatabaseRepository $databaseRepository,
        private readonly Twig $twig,
    ) {
        $this->app = App::getInstance();
    }

    public function index(string $userKey): Response
    {
        if (!$userKey || !$this->databaseRepository->userExists($userKey)) {
            return new RedirectResponse(
                '/send-magic-link'
            );
        }

        $tracks = $this->databaseRepository->getTracksFromUser($userKey);

        $template = $this->twig->getTwig()->load('Default/home.twig');
        $view = $template->render([
            'tracks' => $tracks,
            'userKey' => $userKey,
            'flashes' => $this->app->getSession()->getFlashBag()->all()
        ]);

        return new Response(
            $view,
            Response::HTTP_OK
        );
    }

    public function uploadTrack(string $userKey): Response
    {
        $request = $this->app->getRequest();
        $flashes = $this->app->getSession()->getFlashBag();

        /** @var UploadedFile $file */
        $file = $request->files->get('trackFile');
        $trackName = $request->request->get('trackName');


        if (!$this->isValidFileType($file)) {
            $flashes->add('danger', 'GPX file has an invalid format');

            return new RedirectResponse(
                '/' . $userKey
            );
        }

        $targetFileName = uniqid() . '.' . $file->getClientOriginalExtension();
        if (!$this->uploadFile($userKey, $file, $targetFileName)) {
            $flashes->add('danger', 'Unable to upload the file');

            return new RedirectResponse(
                '/' . $userKey
            );
        }

        if (!$this->updateDatabase($userKey, $trackName, $targetFileName)) {
            $flashes->add('danger', 'Unable to save track');

            return new RedirectResponse(
                '/' . $userKey
            );
        }

        $flashes->add('success', 'New file uploaded');

        return new RedirectResponse(
            '/profile/' . $userKey
        );
    }

    private function uploadFile(string $userKey, UploadedFile $file, string $targetFileName): bool
    {
        $userFolder = $this->configService->getDataPath() . '/' . $userKey;
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
