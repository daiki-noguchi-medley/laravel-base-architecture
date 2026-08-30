<?php

declare(strict_types=1);

namespace App\Support\Transaction;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Transaction の実装。アプリ自身の DB のトランザクションで束ねる。
 *
 * `DB` Facade への依存はこの実装に閉じる (Service 層は Transaction のみに依存する)。
 *
 * 横断インフラの実装クラスは `~Impl` ではなく「実装が何であるか」を名前で示す
 * (CLAUDE.md §4)。Repository / Service と違って、同じ interface に別種の実装
 * (別 DB・分散トランザクション・テスト用の素通し) が並ぶことが前提のため。
 */
final class DatabaseTransaction implements Transaction
{
    public function run(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }
}
