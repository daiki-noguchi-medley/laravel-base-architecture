<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\UserManagement;

use App\Auth\Admin\AdminAuth;
use Demo\Admin\Repository\AdminRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_admin_cannot_list_users(): void
    {
        $this->get('/admin/api/users')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_can_list_users(): void
    {
        DB::table('user')->insert([
            ['name' => 'Alice', 'email' => 'a@example.com', 'password' => Hash::make('x'), 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Bob',   'email' => 'b@example.com', 'password' => Hash::make('x'), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAsAdmin()
            ->getJson('/admin/api/users')
            ->assertOk()
            ->assertJsonCount(2, 'users')
            ->assertJsonPath('users.0.name', 'Alice')
            ->assertJsonPath('users.1.name', 'Bob');
    }

    public function test_soft_deleted_users_are_excluded(): void
    {
        // bulk insert は列数を揃える必要があるので個別に insert する
        DB::table('user')->insert([
            'name' => 'Alive', 'email' => 'alive@example.com', 'password' => Hash::make('x'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('user')->insert([
            'name' => 'Deleted', 'email' => 'gone@example.com', 'password' => Hash::make('x'),
            'created_at' => now(), 'updated_at' => now(), 'deleted_at' => now(),
        ]);

        $this->actingAsAdmin()
            ->getJson('/admin/api/users')
            ->assertOk()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.name', 'Alive');
    }

    private function actingAsAdmin(): self
    {
        $id = DB::table('admin')->insertGetId([
            'name'       => 'Root',
            'email'      => 'root@example.com',
            'password'   => Hash::make('x'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $row = app(AdminRepository::class)->findById($id);

        return $this->actingAs(new AdminAuth($row), 'admin');
    }
}
