<?php

namespace PixelTrack\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MailConnectorService
{
    private HttpClientInterface $httpClient;

    public function __construct()
    {
        $this->httpClient = HttpClient::create();
    }

    /**
     * @param array<mixed> $data
     *
     * @return string
     */
    public function sendRequest(array $data): string
    {
        $response = $this->httpClient->request(
            'POST',
            $_ENV['MAIL_SERVICE_ENDPOINT'],
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $_ENV['MAIL_SERVICE_TOKEN'],
                ],
                'body' => json_encode($data),

            ]
        );

        return $response->getContent(false);
    }
}
