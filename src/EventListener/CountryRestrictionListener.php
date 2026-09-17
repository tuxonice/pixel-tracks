<?php

namespace App\EventListener;

use App\Service\IpApiService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class CountryRestrictionListener
{
    public function __construct(
        private readonly IpApiService $ipApiService,
        private readonly string $allowCountryCode,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $this->allowCountryCode === '') {
            return;
        }

        $countryCode = $this->ipApiService->getCountryByIp((string) $event->getRequest()->getClientIp()) ?? '';
        if (strtolower($countryCode) !== strtolower($this->allowCountryCode)) {
            $event->setResponse(new Response('Forbidden', Response::HTTP_FORBIDDEN));
        }
    }
}
