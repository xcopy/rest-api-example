<?php

declare(strict_types=1);

namespace App\Repositories;

final class EmailVerificationTokenRepository extends Repository
{
    protected function getTable(): string
    {
        return 'email_verification_tokens';
    }
}
