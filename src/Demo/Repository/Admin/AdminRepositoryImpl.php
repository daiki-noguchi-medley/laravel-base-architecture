<?php

declare(strict_types=1);

namespace Demo\Repository\Admin;

use Illuminate\Support\Facades\DB;

final class AdminRepositoryImpl implements AdminRepository
{
    public function findById(int $id): ?AdminRow
    {
        $row = DB::table('admin')->where('id', $id)->first();
        return $row ? AdminRow::fromStdClass($row) : null;
    }

    public function findByEmail(string $email): ?AdminRow
    {
        $row = DB::table('admin')->where('email', $email)->first();
        return $row ? AdminRow::fromStdClass($row) : null;
    }

    public function insert(string $name, string $email, string $hashedPassword): int
    {
        return (int) DB::table('admin')->insertGetId([
            'name'       => $name,
            'email'      => $email,
            'password'   => $hashedPassword,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function updateRememberToken(int $id, ?string $token): void
    {
        DB::table('admin')->where('id', $id)->update([
            'remember_token' => $token,
            'updated_at'     => now(),
        ]);
    }
}
