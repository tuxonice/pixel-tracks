<?php

namespace App\Controller;

use App\Repository\TrackRepository;
use App\Service\FileUploaderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TrackController extends AbstractController
{
    public function __construct(
        private readonly TrackRepository $trackRepository,
        private readonly FileUploaderService $fileUploaderService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/track/info/{trackKey}', name: 'app_track_info', methods: ['GET'])]
    public function index(string $trackKey): Response
    {
        $track = $this->trackRepository->findOneByKey($trackKey);
        if (!$track || $track->getUser() !== $this->getUser()) {
            $this->addFlash('danger', 'Track does not exist');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('Default/track.html.twig', ['track' => $track]);
    }

    #[Route('/track/delete', name: 'app_track_delete', methods: ['POST'])]
    public function deleteTrack(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('track-delete', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $trackKey = (string) $request->request->get('track_key');
        $track = $this->trackRepository->findOneByKey($trackKey);

        if (!$track || $track->getUser() !== $this->getUser()) {
            $this->addFlash('danger', 'Track does not exist!');

            return $this->redirectToRoute('app_profile');
        }

        $trackFilePath = $this->fileUploaderService->getUserDataPath($track->getUser()) . '/' . $track->getFilename();
        if (file_exists($trackFilePath)) {
            unlink($trackFilePath);
        }

        $this->entityManager->remove($track);
        $this->entityManager->flush();

        $this->addFlash('success', 'Track deleted');

        return $this->redirectToRoute('app_profile');
    }
}
