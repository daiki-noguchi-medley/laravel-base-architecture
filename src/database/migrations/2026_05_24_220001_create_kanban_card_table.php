<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kanban カード (ユーザー画面の看板ツール)。
 *
 * - 1 ユーザー = 1 ボード (user_id でスコープ分離)
 * - 4 レーン (Todo / Doing / Review / Done)
 * - 同じレーン内で position による並び順管理 (小さい順に上から表示)
 * - 論理削除 (deleted_at)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_card', function (Blueprint $table) {
            $table->id()->comment('カード ID');
            $table->unsignedBigInteger('user_id')->comment('所有者の user.id');
            $table->string('title')->comment('カードの見出し');
            $table->text('body')->comment('カードの本文');
            $table->string('lane', 16)
                ->comment('レーン。App\\Enum\\Kanban\\KanbanLane の値 (todo/doing/review/done)');
            $table->integer('position')->comment('同一レーン内での並び順 (小さい順に上から)');
            $table->timestamps();
            $table->softDeletes()->comment('論理削除した日時 (NULL = 有効)');

            $table->index(['user_id', 'lane', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_card');
    }
};
