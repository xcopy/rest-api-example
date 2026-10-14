<?php

declare(strict_types=1);

namespace App\Exceptions;

class UnprocessableEntityException extends ServiceException
{
    /** @param array<string, string> $errors */
    public function __construct(private array $errors)
    {
        parent::__construct('Unprocessable entity');
    }

    /** @return array<string, string> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
