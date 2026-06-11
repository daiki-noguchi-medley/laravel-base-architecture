<?php

declare(strict_types=1);

namespace App\Auth\Admin;

use Demo\Admin\Repository\AdminRepository;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

/**
 * Laravel Auth の UserProvider 実装 (管理者用)。
 * config/auth.php の providers.admin.driver = 'adminauth' で登録する。
 */
final class AdminAuthProvider implements UserProvider
{
    public function __construct(
        private readonly AdminRepository $adminRepository,
    ) {}

    public function retrieveById($identifier): ?Authenticatable
    {
        $admin = $this->adminRepository->findById((int) $identifier);
        return $admin ? new AdminAuth($admin) : null;
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        $admin = $this->adminRepository->findById((int) $identifier);
        if ($admin === null || $admin->getRememberToken() === null) {
            return null;
        }
        if (!hash_equals($admin->getRememberToken(), (string) $token)) {
            return null;
        }
        return new AdminAuth($admin);
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        $this->adminRepository->updateRememberToken((int) $user->getAuthIdentifier(), (string) $token);
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $email = $credentials['email'] ?? null;
        if ($email === null) {
            return null;
        }
        $admin = $this->adminRepository->findByEmail((string) $email);
        return $admin ? new AdminAuth($admin) : null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $plain = (string) ($credentials['password'] ?? '');
        return password_verify($plain, $user->getAuthPassword());
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // no-op
    }
}
