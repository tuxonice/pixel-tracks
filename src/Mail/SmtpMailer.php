<?php

namespace PixelTrack\Mail;

use Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PixelTrack\DataTransfers\DataTransferObjects\MailMessageTransfer;

class SmtpMailer implements MailProviderInterface
{
    public function send(MailMessageTransfer $mailMessageTransfer): bool
    {
        [$host, $user, $pass, $port, $encryption] = $this->parseDsn();

        $mailer = new PHPMailer(true);
        $mailer->SMTPDebug = 0;
        $mailer->isSMTP();
        $mailer->Host = $host;
        $mailer->Username = $user;
        $mailer->Password = $pass;
        $mailer->Port = (int)$port;

        match ($encryption) {
            'smtps' => $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS,
            'none' => $mailer->SMTPSecure = '',
            default => $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS,
        };
        $mailer->SMTPAutoTLS = $encryption !== 'none';

        $fromRecipient = $mailMessageTransfer->getFrom();
        $toRecipient = $mailMessageTransfer->getTo();

        $mailer->setFrom($fromRecipient->getEmail(), $fromRecipient->getName());
        $mailer->addAddress($toRecipient->getEmail(), $toRecipient->getName());
        $mailer->isHTML();
        $mailer->Subject = $mailMessageTransfer->getSubject();
        $mailer->Body = $mailMessageTransfer->getHtmlBody();

        return $mailer->send();
    }

    /**
     * @return array<string>
     * @throws Exception
     */
    private function parseDsn(): array
    {
        $dsn = $_ENV['MAIL_PROVIDER_DSN'];
        $pattern = '/^smtp:\/\/([a-zA-Z0-9._\-%]+):([a-zA-Z0-9._\-%]+)@([a-zA-Z0-9.\/\-]+):([0-9]+)(?:\?(.+))?$/';

        if (preg_match($pattern, $dsn, $matches)) {
            $user = urldecode($matches[1]);
            $pass = urldecode($matches[2]);
            $host = $matches[3];
            $port = $matches[4];
            $encryption = 'starttls';

            if (isset($matches[5])) {
                parse_str($matches[5], $query);
                $encryption = $query['encryption'] ?? $encryption;
            }

            return [$host, $user, $pass, $port, $encryption];
        }

        throw new Exception('Unable to parse mail DSN');
    }
}
