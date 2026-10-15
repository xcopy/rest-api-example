<?php

declare(strict_types=1);

namespace App\Repositories;

final class AccessTokenRepository extends Repository
{
    protected function getTable(): string
    {
        return 'access_tokens';
    }
}
