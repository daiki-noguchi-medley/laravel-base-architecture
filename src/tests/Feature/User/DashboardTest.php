<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Auth\User\UserAuth;
use Demo\User\Repository\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')
            ->assertRedirect('/login');
    }

    public function test_dashboard_shows_logged_in_user_name(): void
    {
        $id = DB::table('user')->insertGetId([
            'name'       => 'Alice',
            'email'      => 'alice@example.com',
            'password'   => Hash::make('x'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $row = app(UserRepository::class)->findById($id);
        $this->assertNotNull($row);

        $this->actingAs(new UserAuth($row), 'user')
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Alice')
            ->assertSee('alice@example.com');
    }

    public function test_logout_redirects_to_login(): void
    {
        $id = DB::table('user')->insertGetId([
            'name'       => 'Alice',
            'email'      => 'alice@example.com',
            'password'   => Hash::make('x'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $row = app(UserRepository::class)->findById($id);

        $this->actingAs(new UserAuth($row), 'user')
            ->post('/logout')
            ->assertRedirect('/login');
    }
}
