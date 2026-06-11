<?php

declare(strict_types=1);

namespace App\Http\ViewModel\User\Kanban;

use App\Enum\Kanban\KanbanLane;

/**
 * Kanban ボード Blade に渡す ViewModel。
 *
 * カード本体は JS が `/kanban/cards` を fetch して取得するため、
 * ここで渡すのはレーン定義 (固定) と表示用ラベルだけで十分。
 */
final readonly class KanbanPageViewModel
{
    /**
     * @param list<KanbanLane> $laneList 左から右に並べるレーンの順序固定リスト
     */
    public function __construct(
        public array $laneList,
    ) {}

    public static function build(): self
    {
        return new self(laneList: KanbanLane::orderedList());
    }
}
