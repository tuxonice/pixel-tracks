<?php

namespace PixelTrack\Routes;

use FastRoute\RouteCollector;

class Web implements RouteInterface
{
    public static function routes(RouteCollector $r): void
    {
        $r->addRoute('GET', '/', ['PixelTrack\Controllers\HomeController','index']);
        $r->addRoute('GET', '/send-magic-link', ['PixelTrack\Controllers\MagicLinkController', 'requestMagicLink']);
        $r->addRoute('POST', '/send-magic-link', ['PixelTrack\Controllers\MagicLinkController', 'sendMagicLink']);
        $r->addRoute('GET', '/profile/', ['PixelTrack\Controllers\HomeController','profile']);
        $r->addRoute('GET', '/profile[/?page={page}]', ['PixelTrack\Controllers\HomeController','profile']);
        $r->addRoute('GET', '/login/{loginKey}', ['PixelTrack\Controllers\LoginController','login']);
        $r->addRoute('GET', '/logout', ['PixelTrack\Controllers\LogoutController','index']);
        $r->addRoute('GET', '/map/{trackKey}', ['PixelTrack\Controllers\MapController', 'index']);
        $r->addRoute('POST', '/track/upload', ['PixelTrack\Controllers\UploadController','uploadTrack']);
        $r->addRoute('GET', '/track/info/{trackKey}', ['PixelTrack\Controllers\TrackController','index']);
        $r->addRoute('POST', '/track/delete', ['PixelTrack\Controllers\TrackController','deleteTrack']);
        $r->addRoute('POST', '/share/map/{shareKey}', ['PixelTrack\Controllers\ShareController','share']);
    }
}
