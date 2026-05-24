<?php

declare(strict_types=1);

namespace App\Auth\User;

use Demo\Repository\User\UserRow;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Laravel の Auth ファサードが扱う「認証中のユーザー」を表すクラス。
 * Eloquent モデルではなく、UserRow (DTO) を包んだ readonly オブジェクト。
 *
 * setRememberToken() は no-op (永続化は UserAuthProvider 側で行う)。
 */
final class UserAuth implements Authenticatable
{
    public function __construct(
        private readonly UserRow $row,
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int
    {
        return $this->row->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return $this->row->password;
    }

    public function getRememberToken(): ?string
    {
        return $this->row->rememberToken;
    }

    /**
     * Authenticatable インターフェースの要請で残す。
     * 実際の永続化は UserAuthProvider::updateRememberToken() が担う。
     */
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
        return $this->row->id;
    }

    public function name(): string
    {
        return $this->row->name;
    }

    public function email(): string
    {
        return $this->row->email;
    }
}
