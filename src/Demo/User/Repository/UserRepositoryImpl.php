<?php

declare(strict_types=1);

namespace Demo\User\Repository;

use App\Model\User\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

final class UserRepositoryImpl implements UserRepository
{
    public function findById(int $id): ?User
    {
        $row = DB::table('user')
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->first();
        return $row === null ? null : $this->toModel($row);
    }

    public function findByEmail(string $email): ?User
    {
        $row = DB::table('user')
            ->whereNull('deleted_at')
            ->where('email', $email)
            ->first();
        return $row === null ? null : $this->toModel($row);
    }

    public function insert(string $name, string $email, string $hashedPassword): int
    {
        return (int) DB::table('user')->insertGetId([
            'name'       => $name,
            'email'      => $email,
            'password'   => $hashedPassword,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    public function updateRememberToken(int $id, ?string $token): void
    {
        DB::table('user')->where('id', $id)->update([
            'remember_token' => $token,
            'updated_at'     => Carbon::now(),
        ]);
    }

    public function getUserList(): array
    {
        return DB::table('user')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => $this->toModel($row))
            ->all();
    }

    public function softDelete(int $id): void
    {
        DB::table('user')->where('id', $id)->update([
            'deleted_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * クエリビルダーが返す stdClass を User Model に変換する。
     * カラム名 (snake_case) と型変換の知識はこのクラスに閉じる。
     *
     * @param stdClass $row user テーブルの 1 行
     * @return User 変換済みの Model
     */
    private function toModel(stdClass $row): User
    {
        return new User(
            id: (int) $row->id,
            name: $row->name,
            email: $row->email,
            password: $row->password,
            rememberToken: $row->remember_token,
            createdAt: CarbonImmutable::parse($row->created_at),
            updatedAt: CarbonImmutable::parse($row->updated_at),
        );
    }
}
