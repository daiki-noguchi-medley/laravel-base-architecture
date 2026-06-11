<?php

declare(strict_types=1);

namespace Tests\Feature\Demo\User\Repository;

use Demo\User\Repository\UserRepository;
use App\Model\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class UserRepositoryImplTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $userRepository = app(UserRepository::class);

        $this->assertNull($userRepository->findById(99999));
    }

    public function test_find_by_email_returns_null_when_not_found(): void
    {
        $userRepository = app(UserRepository::class);

        $this->assertNull($userRepository->findByEmail('missing@example.com'));
    }

    public function test_insert_then_find_by_id_returns_row(): void
    {
        $userRepository = app(UserRepository::class);

        $id = $userRepository->insert('Alice', 'alice@example.com', Hash::make('secret'));
        $user = $userRepository->findById($id);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($id, $user->getId());
        $this->assertSame('Alice', $user->getName());
        $this->assertSame('alice@example.com', $user->getEmail());
        $this->assertNull($user->getRememberToken());
        $this->assertTrue(password_verify('secret', $user->getPassword()));
    }

    public function test_find_by_email_returns_row(): void
    {
        $userRepository = app(UserRepository::class);

        $userRepository->insert('Bob', 'bob@example.com', Hash::make('x'));
        $user = $userRepository->findByEmail('bob@example.com');

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('Bob', $user->getName());
    }

    public function test_update_remember_token(): void
    {
        $userRepository = app(UserRepository::class);

        $id = $userRepository->insert('Carol', 'carol@example.com', Hash::make('x'));
        $userRepository->updateRememberToken($id, 'token-xyz');

        $user = $userRepository->findById($id);

        $this->assertSame('token-xyz', $user->getRememberToken());
    }
}
