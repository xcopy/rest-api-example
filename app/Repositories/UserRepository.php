<?php

declare(strict_types=1);

namespace App\Repositories;

class UserRepository extends Repository
{
    protected function getTable(): string
    {
        return 'users';
    }

    /** @return array<string> */
    protected function getColumns(): array
    {
        return [
            'id',
            'email',
            'first_name',
            'last_name',
            'email_verified_at',
            'password_changed_at',
        ];
    }
}
