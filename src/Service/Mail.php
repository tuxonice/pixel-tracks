<?php

namespace PixelTrack\Service;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class Mail
{
    private Mailer $mailer;

    public function __construct()
    {
        $transport = Transport::fromDsn($_ENV['MAILER_DSN']);
        $this->mailer = new Mailer($transport);
    }

    public function send(array $arguments): bool
    {
        $email = new Email();

        $email->from(new Address($arguments['from']['email'], $arguments['from']['name']));

        foreach ($arguments['to'] as $item) {
            $email->addTo(new Address($item['email'], $item['name']));
        }

        $email->subject($arguments['subject']);
        $email->html($arguments['body']);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            return false;
        }

        return true;
    }
}
