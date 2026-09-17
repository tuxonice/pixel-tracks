<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class IpApiService
{
    private const API_URL = 'http://ip-api.com/json/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getCountryByIp(string $ipAddress): ?string
    {
        $cacheItem = $this->cache->getItem('ip_country_code_' . str_replace(['.', ':'], '_', $ipAddress));
        if ($cacheItem->isHit()) {
            $this->logger->info('cached: ' . $ipAddress);

            return $cacheItem->get();
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL . $ipAddress);
            $data = json_decode($response->getContent(), true);

            if (isset($data['status']) && $data['status'] === 'success' && isset($data['countryCode'])) {
                $cacheItem->set($data['countryCode'])->expiresAfter(86400);
                $this->cache->save($cacheItem);
                $this->logger->info('Get country code: ' . $ipAddress . ' - ' . $data['countryCode']);

                return $data['countryCode'];
            }

            $this->logger->warning('Could not get country code: ' . $ipAddress . ' - ' . ($data['message'] ?? 'unknown'));
            $cacheItem->set(null)->expiresAfter(432000);
            $this->cache->save($cacheItem);

            return null;
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());

            return null;
        }
    }
}
