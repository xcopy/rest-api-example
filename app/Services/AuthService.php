<?php

declare(strict_types=1);

namespace App\Services;

use App\Concerns\HasFactory;
use App\Repositories\UserRepository;
use App\Services\Concerns\ValidatesData;
use App\Validators\AuthValidator;
use DI\Attribute\Inject;

final class AuthService
{
    use HasFactory;
    use ValidatesData;

    #[Inject]
    private UserRepository $userRepository;

    protected function validatorClass(): string
    {
        return AuthValidator::class;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function login(array $data): ?array
    {
        $data = $this->validate($data, 'login');

        $user = $this->userRepository
            ->addColumns(['password'])
            ->findOne(['email' => $data['email']]);

        $successful = $user && checkPassword($data['password'], $user['password']);

        return $successful ? $user : null;
    }
}
