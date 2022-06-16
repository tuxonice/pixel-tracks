<?php

namespace PixelTrack\Routes;

use FastRoute\RouteCollector;

interface RouteInterface
{
    public static function routes(RouteCollector $r): void;
}
