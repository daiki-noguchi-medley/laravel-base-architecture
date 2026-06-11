<?php

declare(strict_types=1);

namespace Demo\Admin\Service;

use Demo\Admin\Repository\AdminRepository;
use Illuminate\Support\Facades\Hash;

final class AdminAuthServiceImpl implements AdminAuthService
{
    public function __construct(
        private readonly AdminRepository $adminRepository,
    ) {}

    public function register(string $name, string $email, string $plainPassword): int
    {
        return $this->adminRepository->insert(
            name: $name,
            email: $email,
            hashedPassword: Hash::make($plainPassword),
        );
    }
}
