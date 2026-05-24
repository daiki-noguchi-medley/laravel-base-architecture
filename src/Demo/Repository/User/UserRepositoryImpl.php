<?php

declare(strict_types=1);

namespace Demo\Repository\User;

use Illuminate\Support\Facades\DB;

final class UserRepositoryImpl implements UserRepository
{
    private const string TABLE = 'user';

    public function findById(int $id): ?UserRow
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return $row ? UserRow::fromStdClass($row) : null;
    }

    public function findByEmail(string $email): ?UserRow
    {
        $row = DB::table(self::TABLE)->where('email', $email)->first();
        return $row ? UserRow::fromStdClass($row) : null;
    }

    public function insert(string $name, string $email, string $hashedPassword): int
    {
        return (int) DB::table(self::TABLE)->insertGetId([
            'name'       => $name,
            'email'      => $email,
            'password'   => $hashedPassword,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function updateRememberToken(int $id, ?string $token): void
    {
        DB::table(self::TABLE)->where('id', $id)->update([
            'remember_token' => $token,
            'updated_at'     => now(),
        ]);
    }
}
