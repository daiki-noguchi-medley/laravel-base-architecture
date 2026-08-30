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

        // ⚠ シェルが表示されることだけを見ない。SPA は window.__adminUser を読んで
        //   ヘッダーの表示や API 呼び出しを決めるので、注入が落ちると «画面は出るが
        //   誰としてログインしているか分からない» 状態になる。
        //   AdminAppViewModel での詰め替えが壊れたらここが赤くなる。
        $this->actingAs(new AdminAuth($row), 'admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee('id="admin-app"', false)
            ->assertSee('window.__adminUser', false)
            ->assertSee('"Root"', false)
            ->assertSee('"root@example.com"', false);
    }

    public function test_login_page_does_not_inject_admin_user(): void
    {
        // 未認証のシェル (ログイン画面) には管理者情報を載せない。
        // 載ると、ログアウト後に «戻る» で誰かの情報が残っている状態になり得る。
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('id="admin-app"', false)
            ->assertDontSee('window.__adminUser', false);
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
