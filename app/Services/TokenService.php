<?php

declare(strict_types=1);

namespace App\Services;

class TokenService
{
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    public static function hash(string $value): string
    {
        return hash('sha256', $value);
    }
}
