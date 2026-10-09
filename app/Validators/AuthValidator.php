<?php

namespace App\Validators;

class AuthValidator extends BaseValidator
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
