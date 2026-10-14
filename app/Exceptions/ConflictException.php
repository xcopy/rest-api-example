<?php

declare(strict_types=1);

namespace App\Exceptions;

class ConflictException extends ServiceException
{
    public function __construct(string $message = 'Conflict')
    {
        parent::__construct($message);
    }
}
