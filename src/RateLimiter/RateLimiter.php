<?php

/**
 * Implementation from
 * https://tech.jotform.com/implementing-rate-limiter-with-php-307334598974
 */

namespace PixelTrack\RateLimiter;

use PixelTrack\Cache\Cache;
use PixelTrack\Cache\CacheInterface;

class RateLimiter
{
    private string $prefix;

    private int $maxCapacity;

    private int $refillPeriod;

    private CacheInterface $cache;

    public function __construct(array $options, Cache $cache)
    {
        $this->prefix = $options['prefix'];
        $this->maxCapacity = $options['maxCapacity'];
        $this->refillPeriod = $options['refillPeriod'];
        $this->cache = $cache;
    }

    public function check(string $identifier): bool
    {
        $key = $this->prefix . $identifier;
        // if the bucket does not exist, create it
        if (!$this->hasBucket($key)) {
            $this->createBucket($key);
        }

        $currentTime = time();
        $lastCheck = $this->cache->get($key . 'last_check');
        $tokensToAdd = ($currentTime - $lastCheck) * ($this->maxCapacity / $this->refillPeriod);
        $currentAmmount = $this->cache->get($key);
        // optimization of adding a token every rate ÷ per seconds
        $bucket = $currentAmmount + $tokensToAdd;
        // if is greater than max ammount, set it to max ammount
        $bucket = $bucket > $this->maxCapacity ? $this->maxCapacity : $bucket;
        // set last check time
        $this->cache->put($key . 'last_check', $currentTime, $this->refillPeriod);

        if ($bucket < 1) {
            return false;
        }

        $this->cache->put($key, $bucket - 1, $this->refillPeriod);
        return true;
    }

    private function createBucket(string $key): void
    {
        $this->cache->put($key . 'last_check', time(), $this->refillPeriod);
        $this->cache->put($key, $this->maxCapacity - 1, $this->refillPeriod);
    }

    private function hasBucket(string $key): bool
    {
        return $this->cache->get($key) !== null;
    }
}
