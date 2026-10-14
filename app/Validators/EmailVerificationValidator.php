<?php

declare(strict_types=1);

namespace App\Validators;

use InvalidArgumentException;

class EmailVerificationValidator extends Validator
{
    /**
     * @inheritDoc
     */
    protected function rules(string $scenario, array $context = []): array
    {
        return match ($scenario) {
            'send' => ['email' => 'email'],
            'verify' => ['token' => 'string'],
            default => throw new InvalidArgumentException(sprintf('Unsupported scenario: %s', $scenario)),
        };
    }
}
