<?php

namespace PixelTrack\DataTransfers;

interface TransferInterface
{
    public function toArray(bool $isRecursive = true): array;

    public function fromArray(array $data, bool $ignoreMissingProperty = false): TransferInterface;
}
