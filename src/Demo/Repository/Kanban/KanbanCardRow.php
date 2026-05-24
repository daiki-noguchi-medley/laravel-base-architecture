<?php

declare(strict_types=1);

namespace Demo\Repository\Kanban;

use App\Enums\KanbanLane;
use Carbon\CarbonImmutable;
use stdClass;

/**
 * kanban_card テーブル 1 行を表す readonly DTO。
 */
final readonly class KanbanCardRow
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

    /**
     * クエリビルダーが返す stdClass を KanbanCardRow に変換する。
     */
    public static function fromStdClass(stdClass $row): self
    {
        return new self(
            id: (int) $row->id,
            userId: (int) $row->user_id,
            title: $row->title,
            body: $row->body,
            lane: KanbanLane::from($row->lane),
            position: (int) $row->position,
            createdAt: CarbonImmutable::parse($row->created_at),
            updatedAt: CarbonImmutable::parse($row->updated_at),
        );
    }
}
