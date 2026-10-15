<?php

declare(strict_types=1);

namespace App\Validators;

final class AuthValidator extends Validator
{
    /**
     * @inheritDoc
     */
    protected function rules(string $scenario, array $context = []): array
    {
        return [
            'email' => 'email',
            'password' => 'string',
        ];
    }
}
