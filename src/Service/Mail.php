<?php

namespace PixelTrack\Service;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class Mail
{
    private PHPMailer $mailer;

    public function __construct(MailTransport $transport)
    {
        $this->mailer = new PHPMailer(true);
        $this->mailer->SMTPDebug = $transport->getSmtpDebug();
        if ($transport->isSMTP()) {
            $this->mailer->isSMTP();
        }
        $this->mailer->Host = $transport->getHost();
        $this->mailer->SMTPAuth = $transport->isSmtpAuth();
        $this->mailer->Username = $transport->getUserName();
        $this->mailer->Password = $transport->getPassword();
        $this->mailer->SMTPSecure = $transport->getSmtpSecure();
        $this->mailer->Port = $transport->getPort();
    }

    public function send(array $arguments): bool
    {
        $this->mailer->setFrom($arguments['from']['email'], $arguments['from']['name']);

        foreach ($arguments['to'] as $item) {
            $this->mailer->addAddress($item['email'], $item['name']);
        }

        $this->mailer->isHTML();
        $this->mailer->Subject = $arguments['subject'];
        $this->mailer->Body = $arguments['body'];

        try {
            $this->mailer->send();
        } catch (Exception $e) {
            return false;
        }

        return true;
    }
}
