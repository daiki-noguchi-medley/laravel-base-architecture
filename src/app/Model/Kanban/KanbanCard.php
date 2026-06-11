<?php

declare(strict_types=1);

namespace App\Model\Kanban;

use App\Enum\Kanban\KanbanLane;
use Carbon\CarbonImmutable;

/**
 * kanban_card テーブル 1 行に対応する Model。
 * カラム名・型変換の知識は持たない (変換は Repository Impl の toModel() が担う)。
 */
final readonly class KanbanCard
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $title,
        public string $body,
        public KanbanLane $lane,
        public int $position,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}
}
