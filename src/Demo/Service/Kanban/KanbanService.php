<?php

declare(strict_types=1);

namespace Demo\Service\Kanban;

use App\Enums\KanbanLane;
use Demo\Repository\Kanban\KanbanCardRow;

interface KanbanService
{
    /**
     * 指定ユーザーの全カードを (lane, position) 順で取得する。
     *
     * @param int $userId 対象ユーザー ID (auth ユーザーの id)
     * @return list<KanbanCardRow> 該当なしの場合は空配列
     */
    public function getBoardByUserId(int $userId): array;

    /**
     * カードを TODO レーンの末尾に追加する。
     *
     * @param int $userId 所有ユーザー ID
     * @param string $title タイトル
     * @param string $body 本文
     * @return KanbanCardRow 作成されたカード
     * @throws \Illuminate\Database\QueryException DB エラー時 (FK 違反など)
     * @throws \RuntimeException 作成直後の取得に失敗した場合 (通常発生しない、内部整合性エラー)
     */
    public function createCard(int $userId, string $title, string $body): KanbanCardRow;

    /**
     * カードの title / body を更新する。lane と position は変更しない。
     *
     * @param int $cardId 対象カード ID
     * @param int $userId 認可チェック用のユーザー ID (カードの所有者と一致しないと例外)
     * @param string $title 新しいタイトル
     * @param string $body 新しい本文
     * @return KanbanCardRow 更新後のカード
     * @throws \InvalidArgumentException カードが存在しない / 所有者が違う
     */
    public function updateCard(int $cardId, int $userId, string $title, string $body): KanbanCardRow;

    /**
     * カードを別 lane / position に移動する (DnD)。
     *
     * @param int $cardId 対象カード ID
     * @param int $userId 認可チェック用のユーザー ID
     * @param KanbanLane $lane 移動先 lane
     * @param int $position 移動先 position (0 始まり)
     * @return KanbanCardRow 更新後のカード
     * @throws \InvalidArgumentException カードが存在しない / 所有者が違う
     */
    public function moveCard(int $cardId, int $userId, KanbanLane $lane, int $position): KanbanCardRow;

    /**
     * カードを論理削除する。
     *
     * @param int $cardId 対象カード ID
     * @param int $userId 認可チェック用のユーザー ID
     * @throws \InvalidArgumentException カードが存在しない / 所有者が違う
     */
    public function deleteCard(int $cardId, int $userId): void;
}
