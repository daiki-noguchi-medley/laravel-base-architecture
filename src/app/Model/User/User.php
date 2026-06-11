<?php

declare(strict_types=1);

namespace App\Model\User;

use Carbon\CarbonImmutable;

/**
 * user テーブル 1 行に対応する Model。
 * カラム名・型変換の知識は持たない (変換は Repository Impl の toModel() が担う)。
 */
final readonly class User
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
}
