<?php

declare(strict_types=1);

namespace App\Auth\Admin;

use App\Model\Admin\Admin;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Laravel の Auth ファサードが扱う「認証中の管理者」を表すクラス。
 * Eloquent モデルではなく、Admin Model を包んだ readonly オブジェクト。
 */
final class AdminAuth implements Authenticatable
{
    public function __construct(
        private readonly Admin $admin,
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int
    {
        return $this->admin->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return $this->admin->password;
    }

    public function getRememberToken(): ?string
    {
        return $this->admin->rememberToken;
    }

    public function setRememberToken($value): void
    {
        // no-op (immutable)
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    // -- ビュー / Controller から参照するためのアクセサ --

    public function id(): int
    {
        return $this->admin->id;
    }

    public function name(): string
    {
        return $this->admin->name;
    }

    public function email(): string
    {
        return $this->admin->email;
    }
}
