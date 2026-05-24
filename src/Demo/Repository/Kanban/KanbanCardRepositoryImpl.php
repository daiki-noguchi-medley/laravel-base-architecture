<?php

declare(strict_types=1);

namespace Demo\Repository\Kanban;

use App\Enums\KanbanLane;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
            ->map(fn ($row) => KanbanCardRow::fromStdClass($row))
            ->all();
    }

    public function findById(int $id): ?KanbanCardRow
    {
        $row = DB::table('kanban_card')
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->first();
        return $row ? KanbanCardRow::fromStdClass($row) : null;
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
}
