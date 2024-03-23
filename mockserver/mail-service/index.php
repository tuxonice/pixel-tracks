<?php

require('../vendor/autoload.php');

use PHPMailer\PHPMailer\PHPMailer;
use Symfony\Component\HttpFoundation\Request;

$request = Request::createFromGlobals();

function send(array $arguments): bool
{
    $mailer = new PHPMailer(true);
    $mailer->SMTPDebug = 0;
    $mailer->isSMTP();
    $mailer->Host = 'mailpit';
    $mailer->Username = 'user@example.com';
    $mailer->Password = 'change-me';
    $mailer->Port = 1025;

    $mailer->setFrom('user@example.com', $arguments['from']['name']);
    $mailer->addAddress($arguments['to']['email'], $arguments['to']['name']);
    $mailer->isHTML();
    $mailer->Subject = $arguments['subject'];
    $mailer->Body = $arguments['body']['html'];

    $mailer->send();

    return true;
}

send($request->getPayload()->all());
