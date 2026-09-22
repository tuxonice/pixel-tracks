<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MagicLinkController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoginLinkHandlerInterface $loginLinkHandler,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        #[Autowire(service: 'limiter.magic_link_by_ip')]
        private readonly RateLimiterFactory $magicLinkByIpLimiterFactory,
        #[Autowire(service: 'limiter.magic_link_by_email')]
        private readonly RateLimiterFactory $magicLinkByEmailLimiterFactory,
        private readonly string $emailFrom,
    ) {
    }

    #[Route(path: ['en' => '/en/send-magic-link', 'pt' => '/pt/link-magico'], name: 'app_magic_link_request', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function requestMagicLink(): Response
    {
        return $this->render('Default/magic-link.html.twig');
    }

    #[Route(path: ['en' => '/en/send-magic-link', 'pt' => '/pt/link-magico'], name: 'app_magic_link_send', methods: ['POST'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function sendMagicLink(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('magic-link', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        if (!$this->magicLinkByIpLimiterFactory->create($request->getClientIp())->consume(1)->isAccepted()) {
            return new Response('<h1>429 Too many requests</h1>', Response::HTTP_TOO_MANY_REQUESTS);
        }

        $email = (string) $request->request->get('email');

        if (!$this->magicLinkByEmailLimiterFactory->create(sha1($email))->consume(1)->isAccepted()) {
            return new Response('<h1>429 Too many requests</h1>', Response::HTTP_TOO_MANY_REQUESTS);
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->addFlash('danger', $this->translator->trans('flash.invalid_email'));

            return $this->redirectToRoute('app_magic_link_request');
        }

        $user = $this->userRepository->findOneByEmail($email);
        if (!$user) {
            $user = new User($email);
            $this->entityManager->persist($user);
        }
        $user->setLocale($request->getLocale());
        $this->entityManager->flush();

        $loginLinkDetails = $this->loginLinkHandler->createLoginLink($user);

        $mail = (new Email())
            ->from($this->emailFrom)
            ->to($email)
            ->subject($this->translator->trans('mail.magic_link_subject'))
            ->html($this->renderView('Default/Mail/magic-link-html.html.twig', ['link' => $loginLinkDetails->getUrl()]))
            ->text($this->renderView('Default/Mail/magic-link-text.txt.twig', ['link' => $loginLinkDetails->getUrl()]));

        $this->mailer->send($mail);

        $this->addFlash('success', $this->translator->trans('flash.please_verify_mailbox'));

        return $this->redirectToRoute('app_magic_link_request');
    }
}
