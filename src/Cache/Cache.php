<?php

namespace PixelTrack\Cache;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Cache implements CacheInterface
{
    private FilesystemAdapter $filesystemAdapter;

    public function __construct()
    {
        $this->filesystemAdapter = new FilesystemAdapter(
            'cache',
            0,
            dirname(__DIR__, 2) . '/var/cache'
        );
    }

    public function get(string $key): mixed
    {
        $cacheItem = $this->filesystemAdapter->getItem($key);

        return $cacheItem->get();
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        $cacheItem = $this->filesystemAdapter->getItem($key);
        $cacheItem->set($value);
        $cacheItem->expiresAfter($ttl);

        return $this->filesystemAdapter->save($cacheItem);
    }

    public function delete(string $key): bool
    {
        return $this->filesystemAdapter->deleteItem($key);
    }

    public function has(string $key): bool
    {
        return $this->filesystemAdapter->hasItem($key);
    }
}
