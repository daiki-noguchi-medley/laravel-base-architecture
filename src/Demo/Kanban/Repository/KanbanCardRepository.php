<?php

declare(strict_types=1);

namespace Demo\Kanban\Repository;

use App\Enum\Kanban\KanbanLane;
use App\Model\Kanban\KanbanCard;

interface KanbanCardRepository
{
    /**
     * 指定ユーザーの全カードを (lane, position) 順に取得する。
     * 論理削除されたものは除外する。
     *
     * @param int $userId 対象ユーザー ID
     * @return list<KanbanCard> 該当なしの場合は空配列
     */
    public function getCardListByUserId(int $userId): array;

    /**
     * ID でカードを 1 件取得する。論理削除されたものは除外。
     *
     * @param int $id カード ID
     * @return KanbanCard|null 該当カード (存在しない場合は null)
     */
    public function findById(int $id): ?KanbanCard;

    /**
     * 指定 lane の最大 position を返す (なければ -1)。
     * 新規 INSERT 時に「末尾に追加」する目的で使う。
     *
     * @param int $userId 対象ユーザー ID
     * @param KanbanLane $lane 対象レーン
     * @return int lane 内の最大 position (空なら -1)
     */
    public function maxPositionOfLane(int $userId, KanbanLane $lane): int;

    /**
     * 新規カードを INSERT し、採番された ID を返す。
     *
     * @param int $userId 所有ユーザー ID
     * @param string $title タイトル
     * @param string $body 本文
     * @param KanbanLane $lane 配置 lane
     * @param int $position lane 内 position
     * @return int 採番された ID
     * @throws \Illuminate\Database\QueryException FK 制約違反など DB エラー時
     */
    public function insert(int $userId, string $title, string $body, KanbanLane $lane, int $position): int;

    /**
     * title / body を更新する。
     *
     * @param int $id 対象カード ID
     * @param string $title 新しいタイトル
     * @param string $body 新しい本文
     * @return void
     */
    public function updateContent(int $id, string $title, string $body): void;

    /**
     * lane と position を更新する (DnD で移動した時)。
     *
     * @param int $id 対象カード ID
     * @param KanbanLane $lane 新しい lane
     * @param int $position 新しい position
     * @return void
     */
    public function updatePosition(int $id, KanbanLane $lane, int $position): void;

    /**
     * 論理削除する (deleted_at に現在時刻をセット)。
     *
     * @param int $id 対象カード ID
     * @return void
     */
    public function softDelete(int $id): void;
}
