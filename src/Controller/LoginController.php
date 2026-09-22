<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class LoginController extends AbstractController
{
    #[Route('/login/check', name: 'app_login_check', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function check(): never
    {
        throw new \LogicException('This should never be reached — the login_link authenticator intercepts the request.');
    }
}
