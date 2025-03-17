<?php

namespace PixelTrack\Mail;

use PixelTrack\DataTransfers\DataTransferObjects\MailMessageTransfer;

class LogMailer implements MailProviderInterface
{
    public function send(MailMessageTransfer $mailMessageTransfer): bool
    {
        return true;
    }
}
