<?php

declare(strict_types=1);

namespace App\Repositories;

final class PasswordResetTokenRepository extends Repository
{
    protected function getTable(): string
    {
        return 'password_reset_tokens';
    }
}
