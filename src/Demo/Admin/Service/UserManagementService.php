<?php

declare(strict_types=1);

namespace Demo\Admin\Service;

use App\Model\User\User;

interface UserManagementService
{
    /**
     * 管理画面で表示する全ユーザー一覧を返す (論理削除済みは除外)。
     *
     * @return list<User> 該当なしの場合は空配列
     */
    public function getUserList(): array;

    /**
     * 新規ユーザーを作成する。パスワードはランダム生成し、
     * Hash::make して保存しつつ、戻り値の `plainPassword` で平文を返す
     * (画面で 1 回だけ表示してから破棄する運用)。
     *
     * @param string $name 表示名
     * @param string $email Email
     * @return array{user: User, plainPassword: string} 作成した user と生成された平文パスワード
     * @throws \Illuminate\Database\QueryException Email UNIQUE 制約違反など
     */
    public function createUser(string $name, string $email): array;

    /**
     * ユーザーを論理削除する。
     *
     * @param int $userId 対象ユーザー ID
     * @return void
     * @throws \InvalidArgumentException ユーザーが存在しない / 既に削除済み
     */
    public function deleteUser(int $userId): void;
}
