<?php

declare(strict_types=1);

namespace Demo\User\Repository;

use App\Model\User\User;

interface UserRepository
{
    /**
     * ID で user を 1 件取得する。
     *
     * @param int $id ユーザー ID
     * @return User|null 該当ユーザー (存在しない場合は null)
     */
    public function findById(int $id): ?User;

    /**
     * Email で user を 1 件取得する (認証時の本人特定に使う)。
     *
     * @param string $email Email
     * @return User|null 該当ユーザー (存在しない場合は null)
     */
    public function findByEmail(string $email): ?User;

    /**
     * 新規ユーザーを INSERT し、採番された ID を返す。
     *
     * @param string $name 表示名
     * @param string $email Email
     * @param string $hashedPassword すでに password_hash された値
     * @return int 採番された ID
     * @throws \Illuminate\Database\QueryException UNIQUE 制約違反など DB エラー時
     */
    public function insert(string $name, string $email, string $hashedPassword): int;

    /**
     * Remember Me トークンを更新する。
     *
     * @param int $id 対象ユーザー ID
     * @param string|null $token rememberToken (null でクリア)
     * @return void
     */
    public function updateRememberToken(int $id, ?string $token): void;

    /**
     * 論理削除されていない全ユーザーを id 昇順で取得する (管理画面の一覧用)。
     *
     * @return list<User> 該当なしの場合は空配列
     */
    public function getUserList(): array;

    /**
     * 論理削除する (deleted_at に現在時刻をセット)。
     *
     * @param int $id 対象ユーザー ID
     * @return void
     */
    public function softDelete(int $id): void;
}
