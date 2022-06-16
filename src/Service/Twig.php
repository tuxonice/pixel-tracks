<?php

namespace PixelTrack\Service;

use Twig\Environment;

class Twig
{
    private Environment $twig;

    public function __construct(private readonly TwigLoader $twigLoader)
    {
        $this->twig = new Environment(
            $this->twigLoader->getLoader(),
            [
                'cache' => false,
            ]
        );
    }

    public function getTwig(): Environment
    {
        return $this->twig;
    }
}
