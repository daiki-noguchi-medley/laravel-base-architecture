<?php

declare(strict_types=1);

namespace App\Model\User;

use Carbon\CarbonImmutable;

/**
 * user テーブル 1 行に対応する Model。
 * カラム名・型変換の知識は持たない (変換は Repository Impl の toModel() が担う)。
 * プロパティは公開せず、取り出しは get~ アクセサ経由 (CLAUDE.md §3)。
 */
final readonly class User
{
    public function __construct(
        private int $id,
        private string $name,
        private string $email,
        private string $password,
        private ?string $rememberToken,
        private CarbonImmutable $createdAt,
        private CarbonImmutable $updatedAt,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getRememberToken(): ?string
    {
        return $this->rememberToken;
    }

    public function getCreatedAt(): CarbonImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): CarbonImmutable
    {
        return $this->updatedAt;
    }
}
