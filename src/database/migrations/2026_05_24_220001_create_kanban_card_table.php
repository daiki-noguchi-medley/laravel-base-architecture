<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kanban カード (ユーザー画面の看板ツール)。
 *
 * - 1 ユーザー = 1 ボード (user_id でスコープ分離)
 * - 4 レーン (TODO / DOING / REVIEW / DONE)
 * - 同じレーン内で position による並び順管理 (小さい順に上から表示)
 * - 論理削除 (deleted_at)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_card', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->text('body');
            // lane は app/Enums/KanbanLane の string value (todo/doing/review/done)
            $table->string('lane', 16);
            // 同一 lane 内での並び順 (小さい順に上から)
            $table->integer('position');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'lane', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_card');
    }
};
