<?php

declare(strict_types=1);

namespace App\Middlewares;

final class UpperFilterMiddleware extends FieldFilterMiddleware
{
    protected function filter(string $value): string
    {
        return mb_strtoupper($value, 'UTF-8');
    }
}
