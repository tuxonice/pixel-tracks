<?php

namespace Unit\Service;

use PHPUnit\Framework\TestCase;
use PixelTrack\Service\Config;

class ConfigTest extends TestCase
{
    public function testGetBaseUrlWithHttps(): void
    {
        $_SERVER['SERVER_PROTOCOL'] = 'https';
        $_SERVER['SERVER_NAME'] = 'site-url.local';
        $config = new Config();

        self::assertEquals('https://site-url.local/', $config->getBaseUrl());
    }

    public function testGetBaseUrlWithoutHttps(): void
    {
        $_SERVER['SERVER_PROTOCOL'] = 'http';
        $_SERVER['SERVER_NAME'] = 'site-url.local';
        $config = new Config();

        self::assertEquals('http://site-url.local/', $config->getBaseUrl());
    }
}
