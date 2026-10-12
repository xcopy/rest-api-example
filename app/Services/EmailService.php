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
    protected MailerInterface $mailer;

    #[Inject]
    protected ConfigService $config;

    public function compose(): Email
    {
        $message = new Email();
        $message->from(
            new Address(
                address: $this->config->get('mail.from_address'),
                name: $this->config->get('mail.from_name')
            )
        );

        return $message;
    }

    public function send(Email $message): void
    {
        $this->mailer->send($message);
    }
}
