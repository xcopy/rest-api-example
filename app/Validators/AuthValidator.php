<?php

declare(strict_types=1);

namespace App\Validators;

class AuthValidator extends BaseValidator
{
    /**
     * @inheritDoc
     */
    protected function rules(string $scenario, array $context = []): array
    {
        return match ($scenario) {
            'verify-email' => ['token' => 'string'],
            'send-verification' => ['email' => 'email'],
            'change-password' => [
                'current_password' => 'string|min:1',
                'new_password' => 'string|min:8',
                'new_password_repeat' => 'matchesvalueof<new_password>',
            ],
            default => [
                'email' => 'email',
                'password' => 'string',
            ]
        };
    }
}
