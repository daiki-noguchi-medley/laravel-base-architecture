<?php

declare(strict_types=1);

namespace Tests\Feature\Demo\Repository\Admin;

use Demo\Repository\Admin\AdminRepository;
use Demo\Repository\Admin\AdminRow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminRepositoryImplTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $adminRepository = app(AdminRepository::class);

        $this->assertNull($adminRepository->findById(99999));
    }

    public function test_find_by_email_returns_null_when_not_found(): void
    {
        $adminRepository = app(AdminRepository::class);

        $this->assertNull($adminRepository->findByEmail('missing@example.com'));
    }

    public function test_insert_then_find_by_id_returns_row(): void
    {
        $adminRepository = app(AdminRepository::class);

        $id = $adminRepository->insert('Root', 'root@example.com', Hash::make('secret'));
        $row = $adminRepository->findById($id);

        $this->assertInstanceOf(AdminRow::class, $row);
        $this->assertSame($id, $row->id);
        $this->assertSame('Root', $row->name);
        $this->assertSame('root@example.com', $row->email);
        $this->assertTrue(password_verify('secret', $row->password));
    }

    public function test_find_by_email_returns_row(): void
    {
        $adminRepository = app(AdminRepository::class);

        $adminRepository->insert('Ops', 'ops@example.com', Hash::make('x'));
        $row = $adminRepository->findByEmail('ops@example.com');

        $this->assertInstanceOf(AdminRow::class, $row);
        $this->assertSame('Ops', $row->name);
    }

    public function test_update_remember_token(): void
    {
        $adminRepository = app(AdminRepository::class);

        $id = $adminRepository->insert('Sec', 'sec@example.com', Hash::make('x'));
        $adminRepository->updateRememberToken($id, 'token-admin');

        $row = $adminRepository->findById($id);

        $this->assertSame('token-admin', $row->rememberToken);
    }
}
