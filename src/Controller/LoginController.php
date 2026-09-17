<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

class LoginController extends AbstractController
{
    #[Route('/login/check', name: 'app_login_check', methods: ['GET'])]
    public function check(): never
    {
        throw new \LogicException('This should never be reached — the login_link authenticator intercepts the request.');
    }
}
