<?php

namespace Unit\RateLimiter;

use PHPUnit\Framework\TestCase;
use PixelTrack\Cache\Cache;
use PixelTrack\RateLimiter\RateLimiter;

class RateLimiterTest extends TestCase
{
    public function testCheck(): void
    {
        $rateLimiter = new RateLimiter([
            'prefix' => 'test-prefix-' . uniqid(),
            'maxCapacity' => 5,
            'refillPeriod' => 8
        ], new Cache());


        $this->assertTrue($rateLimiter->check('test-identifier'));
    }

    public function testCheckMultipleTimes(): void
    {
        $rateLimiter = new RateLimiter([
            'prefix' => 'test-prefix-' . uniqid(),
            'maxCapacity' => 5,
            'refillPeriod' => 8
        ], new Cache());

        for ($i = 1; $i <= 5; $i++) {
            $this->assertTrue($rateLimiter->check('test-identifier'));
            usleep(500 * 1000);
        }

        $this->assertFalse($rateLimiter->check('test-identifier'));
    }
}
