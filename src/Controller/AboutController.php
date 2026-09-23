<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AboutController extends AbstractController
{
    #[Route(path: ['en' => '/en/about', 'pt' => '/pt/sobre'], name: 'app_about', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function index(Request $request): Response
    {
        return $this->render(sprintf('Default/about.%s.html.twig', $request->getLocale()));
    }
}
