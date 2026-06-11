<?php

declare(strict_types=1);

namespace Demo\Kanban\Repository;

use App\Enum\Kanban\KanbanLane;
use App\Model\Kanban\KanbanCard;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

final class KanbanCardRepositoryImpl implements KanbanCardRepository
{
    public function getCardListByUserId(int $userId): array
    {
        return DB::table('kanban_card')
            ->whereNull('deleted_at')
            ->where('user_id', $userId)
            ->orderBy('lane')
            ->orderBy('position')
            ->get()
            ->map(fn ($row) => $this->toModel($row))
            ->all();
    }

    public function findById(int $id): ?KanbanCard
    {
        $row = DB::table('kanban_card')
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->first();
        return $row === null ? null : $this->toModel($row);
    }

    public function maxPositionOfLane(int $userId, KanbanLane $lane): int
    {
        $max = DB::table('kanban_card')
            ->whereNull('deleted_at')
            ->where('user_id', $userId)
            ->where('lane', $lane->value)
            ->max('position');

        return $max === null ? -1 : (int) $max;
    }

    public function insert(int $userId, string $title, string $body, KanbanLane $lane, int $position): int
    {
        return (int) DB::table('kanban_card')->insertGetId([
            'user_id'    => $userId,
            'title'      => $title,
            'body'       => $body,
            'lane'       => $lane->value,
            'position'   => $position,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    public function updateContent(int $id, string $title, string $body): void
    {
        DB::table('kanban_card')->where('id', $id)->update([
            'title'      => $title,
            'body'       => $body,
            'updated_at' => Carbon::now(),
        ]);
    }

    public function updatePosition(int $id, KanbanLane $lane, int $position): void
    {
        DB::table('kanban_card')->where('id', $id)->update([
            'lane'       => $lane->value,
            'position'   => $position,
            'updated_at' => Carbon::now(),
        ]);
    }

    public function softDelete(int $id): void
    {
        DB::table('kanban_card')->where('id', $id)->update([
            'deleted_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * クエリビルダーが返す stdClass を KanbanCard Model に変換する。
     * カラム名 (snake_case) と型変換の知識はこのクラスに閉じる。
     *
     * @param stdClass $row kanban_card テーブルの 1 行
     * @return KanbanCard 変換済みの Model
     */
    private function toModel(stdClass $row): KanbanCard
    {
        return new KanbanCard(
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
