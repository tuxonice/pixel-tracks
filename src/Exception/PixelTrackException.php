<?php

namespace PixelTrack\Exception;

class PixelTrackException extends \Exception
{
    /**
     * @var array<mixed>
     */
    protected array $context = [];

    /**
     * @param array<mixed> $context
     *
     * @return $this
     */
    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
