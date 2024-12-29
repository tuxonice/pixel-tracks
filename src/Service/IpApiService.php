<?php

namespace PixelTrack\Service;

use Exception;
use Monolog\Logger;
use PixelTrack\App;
use PixelTrack\Cache\Cache;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class IpApiService
{
    private const API_URL = 'http://ip-api.com/json/';

    private HttpClientInterface $httpClient;

    private Cache $cache;

    private Logger $logger;

    public function __construct()
    {
        $container = App::getInstance()->getContainer();
        $this->logger = $container->get(Logger::class);
        $this->cache = $container->get(Cache::class);
        $this->httpClient = HttpClient::create();
    }

    /**
     * Get the country of an IP address.
     *
     * @param string $ipAddress The IP address to query.
     * @return string|null The country name if found, otherwise null.
     */
    public function getCountryByIp(string $ipAddress): ?string
    {
        // Check if the result is already cached
        $cacheKey = 'ip_country_code_' . $ipAddress;
        if ($this->cache->has($cacheKey)) {
            $this->logger->info('cached: ' . $ipAddress);
            return $this->cache->get($cacheKey);
        }

        try {
            $response = $this->httpClient->request(
                'POST',
                self::API_URL . $ipAddress,
            );

            $data = json_decode($response->getContent(), true);

            if (isset($data['status']) && $data['status'] === 'success' && isset($data['countryCode'])) {
                $this->cache->put($cacheKey, $data['countryCode'], 86400);
                $this->logger->info('Get country code: ' . $ipAddress . ' - ' . $data['countryCode']);

                return $data['countryCode'];
            }
            $this->logger->warning('Could not get country code: ' . $ipAddress . ' - ' . $data['message']);
            $this->cache->put($cacheKey, null, 432000);

            return null;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());

            return null;
        }
    }
}
