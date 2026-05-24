<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class AdminSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('admin')->updateOrInsert(
            ['email' => 'admin@example.com'],
            [
                'name'       => 'テスト管理者',
                'password'   => Hash::make('password'),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        );
    }
}
