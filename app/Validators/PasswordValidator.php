<?php

declare(strict_types=1);

namespace App\Validators;

use InvalidArgumentException;

class PasswordValidator extends Validator
{
    /**
     * @inheritDoc
     */
    protected function rules(string $scenario, array $context = []): array
    {
        $rules = [
            'new_password' => 'string|min:8',
            'new_password_repeat' => 'matchesvalueof<new_password>',
        ];

        return match ($scenario) {
            'forgot' => ['email' => 'email'],
            'reset' => [
                'token' => 'string',
            ] + $rules,
            'change' => [
                'current_password' => 'string|min:1',
            ] + $rules,
            default => throw new InvalidArgumentException(sprintf('Unsupported password scenario: %s', $scenario)),
        };
    }
}
