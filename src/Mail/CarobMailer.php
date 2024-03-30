<?php

namespace PixelTrack\Mail;

use Exception;
use PixelTrack\DataTransfers\DataTransferObjects\MailMessageTransfer;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CarobMailer implements MailProviderInterface
{
    private HttpClientInterface $httpClient;

    public function __construct()
    {
        $this->httpClient = HttpClient::create();
    }

    public function send(MailMessageTransfer $mailMessageTransfer): bool
    {
        [$endpoint, $token] = $this->parseDsn();
        $data = $this->setData($mailMessageTransfer);

        $response = $this->httpClient->request(
            'POST',
            $endpoint,
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
                'body' => json_encode($data),

            ]
        );

        return $response->getStatusCode() === Response::HTTP_OK;
    }

    /**
     * @param MailMessageTransfer $mailMessageTransfer
     *
     * @return array<string,array<string,string>|string>
     */
    private function setData(MailMessageTransfer $mailMessageTransfer): array
    {
        return [
            'from' => [
                'name' => $mailMessageTransfer->getFrom()->getName(),
                'email' => $mailMessageTransfer->getFrom()->getEmail(),
            ],
            'to' => [
                'name' => $mailMessageTransfer->getTo()->getName(),
                'email' => $mailMessageTransfer->getTo()->getEmail(),
            ],
            'subject' => $mailMessageTransfer->getSubject(),
            'body' => [
                "text" => $mailMessageTransfer->getTextBody(),
                "html" => $mailMessageTransfer->getHtmlBody(),
            ]
        ];
    }

    /**
     * @return array<string>
     * @throws Exception
     */
    private function parseDsn(): array
    {
        $dsn = $_ENV['MAIL_PROVIDER_DSN'];
        $pattern = '/(^https?:\/\/)([a-zA-Z0-9._\-%]+)@([a-zA-Z0-9.\/\-]+)$/';

        if (preg_match($pattern, $dsn, $matches)) {
            $protocol = $matches[1];
            $token = $matches[2];
            $endpoint = $matches[3];

            return [$protocol . $endpoint, urldecode($token)];
        }

        throw new Exception('Unable to parse mail DSN');
    }
}
