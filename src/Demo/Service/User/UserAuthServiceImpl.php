<?php

declare(strict_types=1);

namespace Demo\Service\User;

use Demo\Repository\User\UserRepository;
use Illuminate\Support\Facades\Hash;

final class UserAuthServiceImpl implements UserAuthService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}

    public function register(string $name, string $email, string $plainPassword): int
    {
        return $this->userRepo->insert(
            name: $name,
            email: $email,
            hashedPassword: Hash::make($plainPassword),
        );
    }
}
