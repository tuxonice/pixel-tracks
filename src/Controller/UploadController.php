<?php

namespace App\Controller;

use App\Entity\Track;
use App\Entity\User;
use App\Exception\GpxValidationException;
use App\Gps\GpsTrack;
use App\Service\FileUploaderService;
use App\Service\GpxValidator;
use App\Validator\XmlValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UploadController extends AbstractController
{
    public function __construct(
        private readonly XmlValidator $xmlValidator,
        private readonly GpxValidator $gpxValidator,
        private readonly FileUploaderService $fileUploaderService,
        private readonly GpsTrack $gpsTrack,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $gpxSchemaPath,
    ) {
    }

    #[Route('/track/upload', name: 'app_track_upload', methods: ['POST'])]
    public function uploadTrack(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('track-upload', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('trackFile');
        if (!$file) {
            $this->addFlash('danger', 'No file was uploaded');

            return $this->redirectToRoute('app_profile');
        }

        $trackName = trim(htmlspecialchars((string) $request->request->get('trackName', '')));
        if ($trackName === '') {
            $this->addFlash('danger', 'Track name is required');

            return $this->redirectToRoute('app_profile');
        }

        try {
            $this->assertValidGpxFile($file);
        } catch (GpxValidationException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('app_profile');
        }

        /** @var User $user */
        $user = $this->getUser();
        $targetFileName = uniqid() . '.gpx';

        if (!$this->fileUploaderService->uploadFile($user, $file, $targetFileName)) {
            $this->addFlash('danger', 'Unable to upload the file');

            return $this->redirectToRoute('app_profile');
        }

        $trackFilePath = $this->fileUploaderService->getUserDataPath($user) . '/' . $targetFileName;
        $this->gpsTrack->process($trackFilePath);
        $trackInfo = $this->gpsTrack->getInfo();

        $track = new Track($user, $trackName, $targetFileName);
        $track->setTotalPoints($trackInfo['points']);
        $track->setElevation((float) $trackInfo['totalHeight']);
        $track->setDistance((float) $trackInfo['totalDistance']);

        $this->entityManager->persist($track);
        $this->entityManager->flush();

        $this->addFlash('success', 'New file uploaded');

        return $this->redirectToRoute('app_profile');
    }

    private function assertValidGpxFile(UploadedFile $file): void
    {
        try {
            $isValidXml = $this->xmlValidator->isValid(
                (string) file_get_contents($file->getPathname()),
                (string) file_get_contents($this->gpxSchemaPath)
            );

            if (!$isValidXml) {
                throw new GpxValidationException('Invalid GPX file format');
            }

            $this->gpxValidator->validate($file);
        } catch (GpxValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new GpxValidationException('Error validating GPX file: ' . $e->getMessage());
        }
    }
}
