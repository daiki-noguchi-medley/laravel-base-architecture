<?php

declare(strict_types=1);

namespace Demo\Service\Admin;

interface AdminAuthService
{
    /**
     * 新規管理者を登録する。
     * 平文パスワードを受け取り、内部でハッシュ化してから永続化する。
     *
     * @param string $name 表示名
     * @param string $email Email (一意制約あり)
     * @param string $plainPassword 平文パスワード
     * @return int 採番された管理者 ID
     * @throws \Illuminate\Database\QueryException Email 重複等の DB エラー時
     */
    public function register(string $name, string $email, string $plainPassword): int;
}
