<?php

declare(strict_types=1);

namespace App\Exceptions;

final class NotFoundException extends ServiceException
{
    public function __construct(string $message = 'Not found')
    {
        parent::__construct($message);
    }
}
