<?php

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
            'password' => 'min:8',
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
