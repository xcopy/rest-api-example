<?php

namespace App\Validators;

class UserValidator extends BaseValidator
{
    protected function rules(string $scenario, array $context = []): array
    {
        $rules = [
            'email' => 'email|unique:users',
            'password' => 'min:8',
            'first_name' => 'text|min:3|max:100',
            'last_name' => 'text|min:3|max:100',
        ];

        if ($scenario === 'update') {
            $rules['password'] = 'optional|min:8';
        }

        return $rules;
    }
}
