<?php

declare(strict_types=1);

namespace App\Auth\User;

use Demo\User\Repository\UserRepository;
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
        private readonly UserRepository $userRepository,
    ) {}

    public function retrieveById($identifier): ?Authenticatable
    {
        $user = $this->userRepository->findById((int) $identifier);
        return $user ? new UserAuth($user) : null;
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        $user = $this->userRepository->findById((int) $identifier);
        if ($user === null || $user->rememberToken === null) {
            return null;
        }
        if (!hash_equals($user->rememberToken, (string) $token)) {
            return null;
        }
        return new UserAuth($user);
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        $this->userRepository->updateRememberToken((int) $user->getAuthIdentifier(), (string) $token);
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $email = $credentials['email'] ?? null;
        if ($email === null) {
            return null;
        }
        $user = $this->userRepository->findByEmail((string) $email);
        return $user ? new UserAuth($user) : null;
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
