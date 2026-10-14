<?php

declare(strict_types=1);

namespace App\Exceptions;

class NotFoundException extends ServiceException
{
    public function __construct()
    {
        parent::__construct('Not found');
    }
}
