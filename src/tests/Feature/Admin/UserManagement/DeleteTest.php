<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\UserManagement;

use App\Auth\Admin\AdminAuth;
use Demo\Admin\Repository\AdminRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class DeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_admin_cannot_delete_user(): void
    {
        $userId = DB::table('user')->insertGetId([
            'name' => 'Target', 'email' => 't@example.com', 'password' => Hash::make('x'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->deleteJson("/admin/api/users/{$userId}")
            ->assertUnauthorized();

        // 削除されていない
        $this->assertNull(DB::table('user')->where('id', $userId)->value('deleted_at'));
    }

    public function test_admin_can_soft_delete_user(): void
    {
        $userId = DB::table('user')->insertGetId([
            'name' => 'Target', 'email' => 't@example.com', 'password' => Hash::make('x'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAsAdmin()
            ->deleteJson("/admin/api/users/{$userId}")
            ->assertNoContent();

        // 物理 row は残っている (論理削除なので)
        $row = DB::table('user')->where('id', $userId)->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->deleted_at, 'deleted_at がセットされている');
    }

    public function test_deleting_non_existent_user_returns_500_via_invalid_argument(): void
    {
        // Service が InvalidArgumentException を投げる → Laravel default で 500
        $this->actingAsAdmin()
            ->deleteJson('/admin/api/users/9999999')
            ->assertStatus(500);
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
