<?php

namespace PixelTrack\Service;

use PixelTrack\DataTransfers\DataTransferObjects\UserTransfer;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploaderService
{
    public function __construct(
        private readonly Config $configService
    ) {
    }

    public function uploadFile(UserTransfer $userTransfer, UploadedFile $file, string $targetFileName): bool
    {
        $userFolder = $this->configService->getDataPath() . sprintf('/profile-%03d', $userTransfer->getId());
        $splFileInfo = $file->getFileInfo();
        if (!file_exists($userFolder)) {
            if (!mkdir($userFolder)) {
                return false;
            };
        }


        if (!move_uploaded_file($splFileInfo->getRealPath(), $userFolder . '/' . $targetFileName)) {
            return false;
        }

        return true;
    }
}
