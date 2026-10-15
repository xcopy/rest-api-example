<?php

declare(strict_types=1);

namespace App\Validators;

final class UserValidator extends Validator
{
    /**
     * @inheritDoc
     */
    protected function rules(string $scenario, array $context = []): array
    {
        $rules = [
            'first_name' => 'text|min:3|max:100',
            'last_name' => 'text|min:3|max:100',
        ];

        if ($scenario === 'create') {
            $rules = array_merge([
                'email' => 'email|unique:users,' . ($context['user_id'] ?? null),
                'password' => 'string|min:8',
            ], $rules);
        }

        return $rules;
    }
}
