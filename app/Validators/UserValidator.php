<?php

declare(strict_types=1);

namespace App\Validators;

class UserValidator extends BaseValidator
{
    /**
     * @inheritDoc
     */
    protected function rules(string $scenario, array $context = []): array
    {
        $currentUserId = $context['user_id'] ?? null;

        $rules = [
            'email' => "email|unique:users,$currentUserId",
            'password' => 'string|min:8',
            'password_repeat' => 'matchesvalueof<password>',
            'first_name' => 'text|min:3|max:100',
            'last_name' => 'text|min:3|max:100',
        ];

        if ($scenario === 'update') {
            foreach ($rules as $key => $value) {
                $rules[$key] = "optional|$value";
            }
        }

        return $rules;
    }
}
