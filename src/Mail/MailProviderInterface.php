<?php

namespace PixelTrack\Mail;

use PixelTrack\DataTransfers\DataTransferObjects\MailMessageTransfer;

interface MailProviderInterface
{
    public function send(MailMessageTransfer $mailMessageTransfer): bool;
}
