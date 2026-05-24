<?php

declare(strict_types=1);

namespace App\Auth\Admin;

use Demo\Repository\Admin\AdminRow;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Laravel の Auth ファサードが扱う「認証中の管理者」を表すクラス。
 * Eloquent モデルではなく、AdminRow (DTO) を包んだ readonly オブジェクト。
 */
final class AdminAuth implements Authenticatable
{
    public function __construct(
        private readonly AdminRow $row,
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
