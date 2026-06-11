<?php

declare(strict_types=1);

namespace Demo\Admin\Repository;

use App\Model\Admin\Admin;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

final class AdminRepositoryImpl implements AdminRepository
{
    public function findById(int $id): ?Admin
    {
        $row = DB::table('admin')->where('id', $id)->first();
        return $row === null ? null : $this->toModel($row);
    }

    public function findByEmail(string $email): ?Admin
    {
        $row = DB::table('admin')->where('email', $email)->first();
        return $row === null ? null : $this->toModel($row);
    }

    public function insert(string $name, string $email, string $hashedPassword): int
    {
        return (int) DB::table('admin')->insertGetId([
            'name'       => $name,
            'email'      => $email,
            'password'   => $hashedPassword,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    public function updateRememberToken(int $id, ?string $token): void
    {
        DB::table('admin')->where('id', $id)->update([
            'remember_token' => $token,
            'updated_at'     => Carbon::now(),
        ]);
    }

    /**
     * クエリビルダーが返す stdClass を Admin Model に変換する。
     * カラム名 (snake_case) と型変換の知識はこのクラスに閉じる。
     *
     * @param stdClass $row admin テーブルの 1 行
     * @return Admin 変換済みの Model
     */
    private function toModel(stdClass $row): Admin
    {
        return new Admin(
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
