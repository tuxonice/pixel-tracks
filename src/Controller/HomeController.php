<?php

namespace App\Controller;

use App\Entity\User;
use App\Pagination\Paginator;
use App\Repository\TrackRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly TrackRepository $trackRepository,
        private readonly int $paginationIpp,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/', name: 'app_profile', methods: ['GET'])]
    #[Route('/profile/{page}', name: 'app_profile_page', requirements: ['page' => '\d+'], methods: ['GET'])]
    public function profile(Request $request): Response
    {
        $page = (int) ($request->attributes->get('page') ?? $request->query->get('page', 1));
        $page = max(1, $page);

        /** @var User $user */
        $user = $this->getUser();

        $total = $this->trackRepository->countForUser($user);
        $tracks = $this->trackRepository->findPageForUser($user, ($page - 1) * $this->paginationIpp, $this->paginationIpp);

        $paginator = new Paginator($request->getPathInfo(), ['page' => $page, 'ipp' => $this->paginationIpp]);
        $paginator->setItemsTotal($total);
        $paginator->setMidRange(3);
        $paginator->paginate();

        return $this->render('Default/home.html.twig', [
            'tracks' => $tracks,
            'pages' => $paginator->displayPages(),
        ]);
    }
}
