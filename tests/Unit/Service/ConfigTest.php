<?php

namespace Unit\Service;

use PHPUnit\Framework\TestCase;
use PixelTrack\Service\Config;

class ConfigTest extends TestCase
{
    public function testGetBaseUrl(): void
    {
        $_ENV['BASE_URL'] = 'http://site-url.local';
        $config = new Config();

        self::assertEquals('http://site-url.local', $config->getBaseUrl());
    }
}
