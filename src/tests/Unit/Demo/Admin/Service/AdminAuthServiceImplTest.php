<?php

declare(strict_types=1);

namespace Tests\Unit\Demo\Admin\Service;

use Demo\Admin\Repository\AdminRepository;
use Demo\Admin\Service\AdminAuthServiceImpl;
use Tests\TestCase;

final class AdminAuthServiceImplTest extends TestCase
{
    public function test_register_hashes_password_and_delegates_to_repository(): void
    {
        $adminRepository = $this->createMock(AdminRepository::class);

        $adminRepository->expects($this->once())
            ->method('insert')
            ->with(
                'Admin',
                'admin@example.com',
                $this->callback(fn (string $hash) => password_verify('admin-pass', $hash)),
            )
            ->willReturn(7);

        $service = new AdminAuthServiceImpl($adminRepository);

        $id = $service->register('Admin', 'admin@example.com', 'admin-pass');

        $this->assertSame(7, $id);
    }
}
