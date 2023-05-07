<?php

namespace PixelTrack\Service;

class MailTransport
{
    private int $smtpDebug;

    private bool $isSMTP;

    private string $host;

    private bool $smtpAuth;

    private string $userName;

    private string $password;

    private string $smtpSecure;

    private int $port;

    public function __construct()
    {
        $this->smtpDebug = (int)$_ENV['MAILER_SMTP_DEBUG'];
        $this->isSMTP = $_ENV['MAILER_IS_SMTP'] === 'true';
        $this->host = $_ENV['MAILER_HOST'];
        $this->smtpAuth = $_ENV['MAILER_SMTP_AUTH'] === 'true';
        $this->userName = $_ENV['MAILER_USERNAME'];
        $this->password = $_ENV['MAILER_PASSWORD'];
        $this->smtpSecure = $_ENV['MAILER_SMTP_SECURE'];
        $this->port = $_ENV['MAILER_PORT'];
    }

    public function getSmtpDebug(): int
    {
        return $this->smtpDebug;
    }

    public function isSMTP(): bool
    {
        return $this->isSMTP;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function isSmtpAuth(): bool
    {
        return $this->smtpAuth;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getSmtpSecure(): string
    {
        return $this->smtpSecure;
    }

    public function getPort(): int
    {
        return $this->port;
    }
}
