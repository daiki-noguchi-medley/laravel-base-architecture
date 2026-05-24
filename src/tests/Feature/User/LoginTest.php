<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_form_is_shown(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('ユーザーログイン');
    }

    public function test_login_with_valid_credentials_redirects_to_dashboard(): void
    {
        DB::table('user')->insert([
            'name'       => 'Alice',
            'email'      => 'alice@example.com',
            'password'   => Hash::make('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/login', [
            'email'    => 'alice@example.com',
            'password' => 'secret',
        ])->assertRedirect('/dashboard');

        $this->assertTrue(Auth::guard('user')->check());
    }

    public function test_login_with_invalid_password_redirects_back_with_errors(): void
    {
        DB::table('user')->insert([
            'name'       => 'Alice',
            'email'      => 'alice@example.com',
            'password'   => Hash::make('correct'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->from('/login')
            ->post('/login', [
                'email'    => 'alice@example.com',
                'password' => 'wrong',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertFalse(Auth::guard('user')->check());
    }

    public function test_login_with_missing_fields_fails_validation(): void
    {
        $this->from('/login')
            ->post('/login', [])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email', 'password']);
    }
}
