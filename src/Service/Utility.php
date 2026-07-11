<?php

namespace PixelTrack\Service;

use DateTime;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

class Utility
{
    public function __construct(
        private Config $config,
    ) {
    }

    public function generateRandomFileName(UploadedFile $file): string
    {
        return uniqid() . '.gpx';
    }

    public function generateTrackKey(): string
    {
        return Uuid::v4();
    }

    public function currentDateTime(): DateTime
    {
        return new DateTime();
    }

    public function getTrackFileName(int $userId, string $trackFileName): ?string
    {
        $userProfileFolder = sprintf('profile-%03d', $userId);
        $trackFileName = $this->config->getDataPath() . '/' . $userProfileFolder . '/' . $trackFileName;
        if (file_exists($trackFileName)) {
            return $trackFileName;
        }

        return null;
    }
}
