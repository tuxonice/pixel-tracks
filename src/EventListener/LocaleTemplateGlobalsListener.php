<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

#[AsEventListener(event: KernelEvents::REQUEST)]
class LocaleTemplateGlobalsListener
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $routeName = (string) $request->attributes->get('_route', '');
        $bareRouteName = (string) preg_replace('/\.(en|pt)$/', '', $routeName);

        $this->twig->addGlobal('app_route_name', $bareRouteName);
        $this->twig->addGlobal('app_route_params', $request->attributes->get('_route_params', []));
    }
}
