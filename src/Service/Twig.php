<?php

namespace PixelTrack\Service;

use Twig\Environment;
use Twig\Extension\DebugExtension;

class Twig
{
    private Environment $twig;

    public function __construct(private readonly TwigLoader $twigLoader)
    {
        $this->twig = new Environment(
            $this->twigLoader->getLoader(),
            [
                'cache' => false,
                'debug' => Config::isProductionEnvironment() === false,
            ]
        );
        if (Config::isProductionEnvironment() === false) {
            $this->twig->addExtension(new DebugExtension());
        }
    }

    public function getTwig(): Environment
    {
        return $this->twig;
    }
}
