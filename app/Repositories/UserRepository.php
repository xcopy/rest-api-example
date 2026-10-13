<?php

declare(strict_types=1);

namespace App\Repositories;

class UserRepository extends Repository
{
    protected function getTable(): string
    {
        return 'users';
    }
}
