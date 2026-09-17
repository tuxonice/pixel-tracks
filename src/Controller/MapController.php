<?php

namespace App\Controller;

use App\Gps\GpsTrack;
use App\Repository\TrackRepository;
use App\Service\FileUploaderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MapController extends AbstractController
{
    public function __construct(
        private readonly TrackRepository $trackRepository,
        private readonly FileUploaderService $fileUploaderService,
        private readonly GpsTrack $gpsTrack,
    ) {
    }

    #[Route('/map/{trackKey}', name: 'app_track_map', methods: ['GET'])]
    public function index(string $trackKey): Response
    {
        $track = $this->trackRepository->findOneByKey($trackKey);
        if (!$track) {
            $this->addFlash('danger', 'Track file does not exist');

            return $this->redirectToRoute('app_home');
        }

        if ($track->getUser() !== $this->getUser()) {
            $this->addFlash('danger', 'Track does not exist');

            return $this->redirectToRoute('app_profile');
        }

        $trackFilePath = $this->fileUploaderService->getUserDataPath($track->getUser()) . '/' . $track->getFilename();
        if (!file_exists($trackFilePath)) {
            $this->addFlash('danger', 'Track file does not exist');

            return $this->redirectToRoute('app_profile');
        }

        $this->gpsTrack->process($trackFilePath);

        return $this->render('Default/map.html.twig', [
            'title' => $track->getName(),
            'points' => $this->gpsTrack->getJsonPoints(),
            'info' => $this->gpsTrack->getInfo(),
        ]);
    }
}
