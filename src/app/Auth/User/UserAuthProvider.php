<?php

declare(strict_types=1);

namespace App\Auth\User;

use Demo\Repository\User\UserRepository;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

/**
 * Laravel Auth の UserProvider 実装。Repository 経由で user テーブルを引く。
 * Eloquent には触らずクエリビルダー (UserRepositoryImpl) に委譲する。
 *
 * config/auth.php の providers.user.driver = 'userauth' で登録する。
 */
final class UserAuthProvider implements UserProvider
{
    public function __construct(
        private readonly UserRepository $userRepo,
    ) {}

    public function retrieveById($identifier): ?Authenticatable
    {
        $row = $this->userRepo->findById((int) $identifier);
        return $row ? new UserAuth($row) : null;
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        $row = $this->userRepo->findById((int) $identifier);
        if ($row === null || $row->rememberToken === null) {
            return null;
        }
        if (!hash_equals($row->rememberToken, (string) $token)) {
            return null;
        }
        return new UserAuth($row);
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        $this->userRepo->updateRememberToken((int) $user->getAuthIdentifier(), (string) $token);
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $email = $credentials['email'] ?? null;
        if ($email === null) {
            return null;
        }
        $row = $this->userRepo->findByEmail((string) $email);
        return $row ? new UserAuth($row) : null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $plain = (string) ($credentials['password'] ?? '');
        return password_verify($plain, $user->getAuthPassword());
    }

    /**
     * Laravel 11+ で UserProvider に追加されたメソッド。
     * 必要に応じてハッシュアルゴリズム更新時に再ハッシュする (今回は no-op)。
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // no-op (将来 password_needs_rehash() のチェックを入れるならここで)
    }
}
