<?php

namespace PixelTrack\Service;

use DateTime;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

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

    public function generateTrackKey(): string
    {
        return Uuid::v4();
    }

    public function currentDateTime(): DateTime
    {
        return new DateTime();
    }
}
