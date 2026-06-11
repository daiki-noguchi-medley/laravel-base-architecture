<?php

declare(strict_types=1);

namespace App\Http\Resource\User\Kanban;

use App\Model\Kanban\KanbanCard;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Kanban カード 1 件分の JSON 表現。
 *
 * @implements Arrayable<string, int|string>
 */
final readonly class KanbanCardResource implements Arrayable
{
    public function __construct(
        private KanbanCard $card,
    ) {}

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     body: string,
     *     lane: string,
     *     position: int,
     *     updated_at: string
     * }
     */
    public function toArray(): array
    {
        return [
            'id'         => $this->card->id,
            'title'      => $this->card->title,
            'body'       => $this->card->body,
            'lane'       => $this->card->lane->value,
            'position'   => $this->card->position,
            'updated_at' => $this->card->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
