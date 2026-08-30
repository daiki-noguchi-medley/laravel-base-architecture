<?php

declare(strict_types=1);

namespace App\Support\Transaction;

use Closure;

/**
 * 複数 Repository の書き込みを 1 トランザクションで束ねるための抽象。
 *
 * Service 層は永続化 Facade (`DB::`) を直接触らない (CLAUDE.md §4 鉄則) ため、
 * トランザクション境界だけはこの interface 経由で張る。
 * 「ここだけ DB:: を書いてよい」という例外を作らないための土台。
 *
 * DI バインドは RepositoryServiceProvider
 * (Repository でも Service でもないが、永続化に近い共通基盤のため)。
 */
interface Transaction
{
    /**
     * クロージャを 1 トランザクション内で実行し、その戻り値を返す (例外時はロールバック)。
     *
     * ⚠ クロージャの中で早期 return して「途中まで」で抜けない。
     *   巻き戻すのは例外だけなので、return した時点でそこまでの書き込みが確定する。
     *   複数件を書く処理は「全部の検証 → 全部の書き込み」の順にする (CLAUDE.md §6)。
     *
     * ⚠ 中で SQL エラーを握り潰さない。PostgreSQL はトランザクション中に 1 つでも
     *   SQL エラーが起きると以降の文がすべて失敗し、COMMIT が黙って ROLLBACK になる。
     *   catch してログだけ残すと「成功ログが出ているのに 1 行も書かれていない」になる。
     *
     * @template T
     *
     * @param  Closure(): T  $callback トランザクション内で実行する処理
     * @return T クロージャの戻り値
     */
    public function run(Closure $callback): mixed;
}
