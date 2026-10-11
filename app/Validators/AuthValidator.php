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
            default => [
                'email' => 'email',
                'password' => 'string',
            ]
        };
    }
}
