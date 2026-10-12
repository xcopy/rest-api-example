<?php

declare(strict_types=1);

namespace App\Mailer;

use App\Services\DateTimeService;
use App\Services\TokenService;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

final class FileTransport extends AbstractTransport
{
    public function __construct(private string $directory)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException(sprintf('Unable to create mail directory "%s".', $this->directory));
        }

        $filename = sprintf(
            '%s-%s.eml',
            DateTimeService::now()->format('YmdHis'),
            TokenService::generateToken(8)
        );

        $path = $this->directory . '/' . $filename;

        if (file_put_contents($path, $message->toString(), LOCK_EX) === false) {
            throw new RuntimeException(sprintf('Unable to write email to "%s".', $path));
        }
    }

    public function __toString(): string
    {
        return sprintf('file://%s', $this->directory);
    }
}
