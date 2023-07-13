<?php

namespace Unit\Controllers;

use PHPUnit\Framework\TestCase;
use PixelTrack\Controllers\NotAllowed;
use Symfony\Component\HttpFoundation\Response;

class NotAllowedControllerTest extends TestCase
{
    public function testRequestNotAllowed(): void
    {
        $notAllowed = new NotAllowed();
        $expectedResponse = new Response(
            'Method Not Allowed',
            405,
        );

        $this->assertEquals(
            $expectedResponse,
            $notAllowed->index()
        );
    }
}
