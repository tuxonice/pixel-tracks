<?php

namespace Unit\Cache;

use PHPUnit\Framework\TestCase;
use PixelTrack\Cache\Cache;

class CacheTest extends TestCase
{
    public function testHasGetPutDelete(): void
    {
        $cache = new Cache();

        $cache->delete('some-key');

        self::assertFalse($cache->has('no-existing-key'));
        self::assertNull($cache->get('some-key'));
        $cache->put('some-key', '123', 1);
        self::assertTrue($cache->has('some-key'));
        self::assertEquals('123', $cache->get('some-key'));
        sleep(2);
        self::assertNull($cache->get('some-key'));
    }
}
