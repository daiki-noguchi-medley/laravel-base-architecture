<?php

declare(strict_types=1);

namespace Demo\User\Service;

interface UserAuthService
{
    /**
     * 新規ユーザーを登録する。
     * 平文パスワードを受け取り、内部でハッシュ化してから永続化する。
     *
     * @param string $name 表示名
     * @param string $email Email (一意制約あり)
     * @param string $plainPassword 平文パスワード (8 文字以上を想定、検証は呼び出し側)
     * @return int 採番されたユーザー ID
     * @throws \Illuminate\Database\QueryException Email 重複等の DB エラー時
     */
    public function register(string $name, string $email, string $plainPassword): int;
}
