<?php

namespace PixelTrack\Cache;

interface CacheInterface
{
    public function get(string $key): mixed;

    public function put(string $key, mixed $value, int $ttl): bool;

    public function delete(string $key): bool;
}
