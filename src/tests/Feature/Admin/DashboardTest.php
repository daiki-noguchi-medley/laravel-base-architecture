<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Auth\Admin\AdminAuth;
use Demo\Admin\Repository\AdminRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_arbitrary_admin_subpath_also_requires_authentication(): void
    {
        // React SPA の任意パスもサーバー側で auth:admin に吸われる
        $this->get('/admin/anywhere/deep')
            ->assertRedirect('/admin/login');
    }

    public function test_dashboard_is_shown_when_authenticated(): void
    {
        $id = DB::table('admin')->insertGetId([
            'name'       => 'Root',
            'email'      => 'root@example.com',
            'password'   => Hash::make('x'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $row = app(AdminRepository::class)->findById($id);
        $this->assertNotNull($row);

        $this->actingAs(new AdminAuth($row), 'admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee('id="admin-app"', false);
    }

    public function test_logout_redirects_to_admin_login(): void
    {
        $id = DB::table('admin')->insertGetId([
            'name'       => 'Root',
            'email'      => 'root@example.com',
            'password'   => Hash::make('x'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $row = app(AdminRepository::class)->findById($id);

        $this->actingAs(new AdminAuth($row), 'admin')
            ->post('/admin/logout')
            ->assertRedirect('/admin/login');
    }
}
