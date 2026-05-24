<?php

declare(strict_types=1);

namespace Demo\Repository\Admin;

use Carbon\CarbonImmutable;
use stdClass;

/**
 * admin テーブル 1 行を表す readonly DTO。
 */
final readonly class AdminRow
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
