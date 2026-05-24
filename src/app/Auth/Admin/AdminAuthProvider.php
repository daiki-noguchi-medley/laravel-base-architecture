<?php

declare(strict_types=1);

namespace App\Auth\Admin;

use Demo\Repository\Admin\AdminRepository;
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
        $row = $this->adminRepo->findById((int) $identifier);
        return $row ? new AdminAuth($row) : null;
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        $row = $this->adminRepo->findById((int) $identifier);
        if ($row === null || $row->rememberToken === null) {
            return null;
        }
        if (!hash_equals($row->rememberToken, (string) $token)) {
            return null;
        }
        return new AdminAuth($row);
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        $this->adminRepo->updateRememberToken((int) $user->getAuthIdentifier(), (string) $token);
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $email = $credentials['email'] ?? null;
        if ($email === null) {
            return null;
        }
        $row = $this->adminRepo->findByEmail((string) $email);
        return $row ? new AdminAuth($row) : null;
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
