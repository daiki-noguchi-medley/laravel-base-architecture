<?php

declare(strict_types=1);

namespace Tests\Feature\Demo\Admin\Repository;

use Demo\Admin\Repository\AdminRepository;
use App\Model\Admin\Admin;
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
        $admin = $adminRepository->findById($id);

        $this->assertInstanceOf(Admin::class, $admin);
        $this->assertSame($id, $admin->getId());
        $this->assertSame('Root', $admin->getName());
        $this->assertSame('root@example.com', $admin->getEmail());
        $this->assertTrue(password_verify('secret', $admin->getPassword()));
    }

    public function test_find_by_email_returns_row(): void
    {
        $adminRepository = app(AdminRepository::class);

        $adminRepository->insert('Ops', 'ops@example.com', Hash::make('x'));
        $admin = $adminRepository->findByEmail('ops@example.com');

        $this->assertInstanceOf(Admin::class, $admin);
        $this->assertSame('Ops', $admin->getName());
    }

    public function test_update_remember_token(): void
    {
        $adminRepository = app(AdminRepository::class);

        $id = $adminRepository->insert('Sec', 'sec@example.com', Hash::make('x'));
        $adminRepository->updateRememberToken($id, 'token-admin');

        $admin = $adminRepository->findById($id);

        $this->assertSame('token-admin', $admin->getRememberToken());
    }
}
