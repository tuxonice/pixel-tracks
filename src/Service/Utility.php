<?php

namespace PixelTrack\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class Utility
{
    public function generateCsrfToken(): string
    {
        return sha1(uniqid('', true));
    }

    public function generateRandomFileName(UploadedFile $file): string
    {
        return uniqid() . '.' . $file->getClientOriginalExtension();
    }
}
