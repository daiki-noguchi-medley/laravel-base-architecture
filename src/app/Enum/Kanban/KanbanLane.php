<?php

declare(strict_types=1);

namespace App\Enum\Kanban;

/**
 * Kanban カードの 4 つのレーン。
 *
 * - value (DB に保存する文字列) は小文字スネークケース
 * - case 名は大文字スネークケース (CLAUDE.md §1)
 * - 表示順は次のメソッドで定義 → {@see self::orderedList()}
 */
enum KanbanLane: string
{
    case TODO   = 'todo';
    case DOING  = 'doing';
    case REVIEW = 'review';
    case DONE   = 'done';

    /**
     * 画面で左から右に並べるときの順序で返す。
     *
     * @return list<self>
     */
    public static function orderedList(): array
    {
        return [
            self::TODO,
            self::DOING,
            self::REVIEW,
            self::DONE,
        ];
    }

    /**
     * 画面表示用のラベル。
     */
    public function label(): string
    {
        return match ($this) {
            self::TODO   => 'TODO',
            self::DOING  => 'DOING',
            self::REVIEW => 'REVIEW',
            self::DONE   => 'DONE',
        };
    }
}
