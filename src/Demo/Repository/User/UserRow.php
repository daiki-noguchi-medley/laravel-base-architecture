<?php

declare(strict_types=1);

namespace Demo\Repository\User;

use Carbon\CarbonImmutable;
use stdClass;

/**
 * user テーブル 1 行を表す readonly DTO。
 * Eloquent モデルを Service 層に漏らさないための変換層。
 */
final readonly class UserRow
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $password,
        public ?string $rememberToken,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * クエリビルダーが返す stdClass を UserRow に変換する。
     */
    public static function fromStdClass(stdClass $row): self
    {
        return new self(
            id: (int) $row->id,
            name: $row->name,
            email: $row->email,
            password: $row->password,
            rememberToken: $row->remember_token,
            createdAt: CarbonImmutable::parse($row->created_at),
            updatedAt: CarbonImmutable::parse($row->updated_at),
        );
    }
}
