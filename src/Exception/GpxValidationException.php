<?php

namespace App\Exception;

class GpxValidationException extends \RuntimeException
{
    /** @param array<string, string> $parameters */
    public function __construct(string $translationKey, private readonly array $parameters = [])
    {
        parent::__construct($translationKey);
    }

    /** @return array<string, string> */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
