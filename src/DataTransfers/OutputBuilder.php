<?php

namespace PixelTrack\DataTransfers;

class OutputBuilder
{
    public function getOutputPath(): string
    {
        return dirname(__DIR__) . '/DataTransferObjects';
    }

    public function save(string $classContent, string $className)
    {
        $filename = $this->getOutputPath() . '/' . $className . '.php';
        file_put_contents($filename, $classContent);
        chmod($filename, 0777);
    }
}
