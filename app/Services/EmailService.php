<?php

declare(strict_types=1);

namespace App\Services;

use DI\Attribute\Inject;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailService
{
    #[Inject]
    private MailerInterface $mailer;

    public function compose(): Email
    {
        $message = new Email();
        $message->from(
            new Address(
                address: config('mail.from_address'),
                name: config('mail.from_name')
            )
        );

        return $message;
    }

    public function send(Email $message): void
    {
        $this->mailer->send($message);
    }
}
