<?php

declare(strict_types=1);

namespace App\Repositories;

class AccessTokenRepository extends Repository
{
    protected function getTable(): string
    {
        return 'access_tokens';
    }
}
