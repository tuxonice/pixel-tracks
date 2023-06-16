<?php

namespace PixelTrack\Routes;

use FastRoute\RouteCollector;

class Web implements RouteInterface
{
    public static function routes(RouteCollector $r): void
    {
        $r->addRoute('GET', '/', ['PixelTrack\Controllers\MagicLinkController','requestMagicLink']);
        $r->addRoute('GET', '/send-magic-link[/]', ['PixelTrack\Controllers\MagicLinkController', 'requestMagicLink']);
        $r->addRoute('POST', '/send-magic-link', ['PixelTrack\Controllers\MagicLinkController', 'sendMagicLink']);
        $r->addRoute('GET', '/profile[/]', ['PixelTrack\Controllers\HomeController','profile']);
        $r->addRoute('GET', '/logout[/]', ['PixelTrack\Controllers\LogoutController','index']);
        $r->addRoute('GET', '/profile/{userKey}[/]', ['PixelTrack\Controllers\HomeController','index']);
        $r->addRoute('GET', '/profile/{userKey}/map/{trackKey}[/]', ['PixelTrack\Controllers\MapController', 'index']);
        $r->addRoute('POST', '/upload/{userKey}/upload-track', ['PixelTrack\Controllers\UploadController','uploadTrack']);
        $r->addRoute('POST', '/profile/{userKey}/delete-track', ['PixelTrack\Controllers\HomeController','deleteTrack']);
        $r->addRoute('POST', '/share/map/{shareKey}', ['PixelTrack\Controllers\ShareController','share']);
    }
}
