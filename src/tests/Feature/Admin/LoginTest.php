<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_form_is_shown_as_react_mount_point(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('id="admin-app"', false);
    }

    public function test_login_with_valid_credentials_redirects_to_admin(): void
    {
        DB::table('admin')->insert([
            'name'       => 'Root',
            'email'      => 'root@example.com',
            'password'   => Hash::make('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/admin/login', [
            'email'    => 'root@example.com',
            'password' => 'secret',
        ])->assertRedirect('/admin');

        $this->assertTrue(Auth::guard('admin')->check());
    }

    public function test_login_with_invalid_password_redirects_back_with_errors(): void
    {
        DB::table('admin')->insert([
            'name'       => 'Root',
            'email'      => 'root@example.com',
            'password'   => Hash::make('correct'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->from('/admin/login')
            ->post('/admin/login', [
                'email'    => 'root@example.com',
                'password' => 'wrong',
            ])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');

        $this->assertFalse(Auth::guard('admin')->check());
    }

    public function test_login_with_missing_fields_fails_validation(): void
    {
        $this->from('/admin/login')
            ->post('/admin/login', [])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_user_guard_cannot_login_to_admin(): void
    {
        // user テーブルに同じ email がいても admin guard では認証されない
        DB::table('user')->insert([
            'name'       => 'Alice',
            'email'      => 'alice@example.com',
            'password'   => Hash::make('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->from('/admin/login')
            ->post('/admin/login', [
                'email'    => 'alice@example.com',
                'password' => 'secret',
            ])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');

        $this->assertFalse(Auth::guard('admin')->check());
    }
}
