<?php

declare(strict_types=1);

namespace App\Enum\Kanban;

/**
 * Kanban カードの 4 つのレーン。
 *
 * - case 名はパスカルケース、value (DB に保存する文字列) は小文字スネークケース (CLAUDE.md §1)
 * - value は DB の既存行が参照しているため、表記を揃える目的で変えない
 * - 表示順は次のメソッドで定義 → {@see self::orderedList()}
 */
enum KanbanLane: string
{
    case Todo   = 'todo';
    case Doing  = 'doing';
    case Review = 'review';
    case Done   = 'done';

    /**
     * 画面で左から右に並べるときの順序で返す。
     *
     * @return list<self>
     */
    public static function orderedList(): array
    {
        return [
            self::Todo,
            self::Doing,
            self::Review,
            self::Done,
        ];
    }

    /**
     * 画面表示用のラベル。
     */
    public function label(): string
    {
        return match ($this) {
            self::Todo   => 'TODO',
            self::Doing  => 'DOING',
            self::Review => 'REVIEW',
            self::Done   => 'DONE',
        };
    }
}
