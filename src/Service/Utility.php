<?php

namespace PixelTrack\Service;

class Utility
{
    public function generateCsrfToken(): string
    {
        return sha1(uniqid('', true));
    }
}
