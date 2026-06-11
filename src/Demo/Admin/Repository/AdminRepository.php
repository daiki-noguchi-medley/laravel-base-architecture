<?php

declare(strict_types=1);

namespace Demo\Admin\Repository;

use App\Model\Admin\Admin;

interface AdminRepository
{
    /**
     * ID で admin を 1 件取得する。
     *
     * @param int $id 管理者 ID
     * @return Admin|null 該当管理者 (存在しない場合は null)
     */
    public function findById(int $id): ?Admin;

    /**
     * Email で admin を 1 件取得する (認証時の本人特定に使う)。
     *
     * @param string $email Email
     * @return Admin|null 該当管理者 (存在しない場合は null)
     */
    public function findByEmail(string $email): ?Admin;

    /**
     * 新規管理者を INSERT し、採番された ID を返す。
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
     * @param int $id 対象管理者 ID
     * @param string|null $token rememberToken (null でクリア)
     * @return void
     */
    public function updateRememberToken(int $id, ?string $token): void;
}
