<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\UserManagement;

use App\Auth\Admin\AdminAuth;
use Demo\Repository\Admin\AdminRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class CreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_admin_cannot_create_user(): void
    {
        $this->postJson('/admin/api/users', [
            'name'  => 'New User',
            'email' => 'new@example.com',
        ])->assertUnauthorized();
    }

    public function test_admin_can_create_user_and_receives_plain_password(): void
    {
        $response = $this->actingAsAdmin()
            ->postJson('/admin/api/users', [
                'name'  => 'Charlie',
                'email' => 'charlie@example.com',
            ])
            ->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'created_at'], 'plain_password']);

        $plainPassword = $response->json('plain_password');
        $this->assertIsString($plainPassword);
        $this->assertGreaterThanOrEqual(12, strlen($plainPassword), '生成パスワードは 12 文字以上である');

        // DB に hash 済みパスワードで保存されている
        $userRow = DB::table('user')->where('email', 'charlie@example.com')->first();
        $this->assertNotNull($userRow);
        $this->assertNotEquals($plainPassword, $userRow->password, '平文をそのまま保存していない');
        $this->assertTrue(Hash::check($plainPassword, $userRow->password));
    }

    public function test_duplicate_email_is_rejected_with_422(): void
    {
        DB::table('user')->insert([
            'name'       => 'Existing',
            'email'      => 'dup@example.com',
            'password'   => Hash::make('x'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsAdmin()
            ->postJson('/admin/api/users', [
                'name'  => 'Another',
                'email' => 'dup@example.com',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_invalid_email_format_is_rejected(): void
    {
        $this->actingAsAdmin()
            ->postJson('/admin/api/users', [
                'name'  => 'NoAtSign',
                'email' => 'not-an-email',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_name_is_required(): void
    {
        $this->actingAsAdmin()
            ->postJson('/admin/api/users', [
                'name'  => '',
                'email' => 'ok@example.com',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
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
