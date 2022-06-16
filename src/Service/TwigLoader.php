<?php

namespace PixelTrack\Service;

use Twig\Loader\FilesystemLoader;

class TwigLoader
{
    public function getLoader(): FilesystemLoader
    {
        return new FilesystemLoader(dirname(__DIR__) . '/Templates');
    }
}
