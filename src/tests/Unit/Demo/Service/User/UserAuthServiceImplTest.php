<?php

declare(strict_types=1);

namespace Tests\Unit\Demo\Service\User;

use Demo\Repository\User\UserRepository;
use Demo\Service\User\UserAuthServiceImpl;
use Tests\TestCase;

final class UserAuthServiceImplTest extends TestCase
{
    public function test_register_hashes_password_and_delegates_to_repository(): void
    {
        // UserRepository は interface に対して mock する (規約: docs/testing.md)
        $userRepository = $this->createMock(UserRepository::class);

        // insert() に渡される hashedPassword は、元の平文を password_verify で
        // 検証できる値であること = 内部で Hash::make を通している
        $userRepository->expects($this->once())
            ->method('insert')
            ->with(
                'Alice',
                'alice@example.com',
                $this->callback(fn (string $hash) => password_verify('secret-pass', $hash)),
            )
            ->willReturn(42);

        $service = new UserAuthServiceImpl($userRepository);

        $id = $service->register('Alice', 'alice@example.com', 'secret-pass');

        $this->assertSame(42, $id);
    }
}
