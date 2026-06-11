<?php

declare(strict_types=1);

namespace App\Model\Kanban;

use App\Enum\Kanban\KanbanLane;
use Carbon\CarbonImmutable;

/**
 * kanban_card テーブル 1 行に対応する Model。
 * カラム名・型変換の知識は持たない (変換は Repository Impl の toModel() が担う)。
 * プロパティは公開せず、取り出しは get~ アクセサ経由 (CLAUDE.md §3)。
 */
final readonly class KanbanCard
{
    public function __construct(
        private int $id,
        private int $userId,
        private string $title,
        private string $body,
        private KanbanLane $lane,
        private int $position,
        private CarbonImmutable $createdAt,
        private CarbonImmutable $updatedAt,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getLane(): KanbanLane
    {
        return $this->lane;
    }

    public function getPosition(): int
    {
        return $this->position;
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
