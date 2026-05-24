<?php

declare(strict_types=1);

namespace Demo\Repository\User;

use Illuminate\Support\Facades\DB;

final class UserRepositoryImpl implements UserRepository
{
    public function findById(int $id): ?UserRow
    {
        $row = DB::table('user')
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->first();
        return $row ? UserRow::fromStdClass($row) : null;
    }

    public function findByEmail(string $email): ?UserRow
    {
        $row = DB::table('user')
            ->whereNull('deleted_at')
            ->where('email', $email)
            ->first();
        return $row ? UserRow::fromStdClass($row) : null;
    }

    public function insert(string $name, string $email, string $hashedPassword): int
    {
        return (int) DB::table('user')->insertGetId([
            'name'       => $name,
            'email'      => $email,
            'password'   => $hashedPassword,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function updateRememberToken(int $id, ?string $token): void
    {
        DB::table('user')->where('id', $id)->update([
            'remember_token' => $token,
            'updated_at'     => now(),
        ]);
    }

    public function getUserList(): array
    {
        return DB::table('user')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => UserRow::fromStdClass($row))
            ->all();
    }

    public function softDelete(int $id): void
    {
        DB::table('user')->where('id', $id)->update([
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
