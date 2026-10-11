<?php

declare(strict_types=1);

namespace App\Middlewares;

final class LowerFilterMiddleware extends FieldFilterMiddleware
{
    protected function filter(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }
}
