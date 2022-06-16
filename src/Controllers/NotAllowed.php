<?php

namespace PixelTrack\Controllers;

use Symfony\Component\HttpFoundation\Response;

class NotAllowed
{
    public function index(): Response
    {
        return new Response(
            'Method Not Allowed',
            Response::HTTP_METHOD_NOT_ALLOWED
        );
    }
}
