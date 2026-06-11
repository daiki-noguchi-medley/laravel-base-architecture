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
        $row = $userRepository->findById($id);

        $this->assertInstanceOf(User::class, $row);
        $this->assertSame($id, $row->id);
        $this->assertSame('Alice', $row->name);
        $this->assertSame('alice@example.com', $row->email);
        $this->assertNull($row->rememberToken);
        $this->assertTrue(password_verify('secret', $row->password));
    }

    public function test_find_by_email_returns_row(): void
    {
        $userRepository = app(UserRepository::class);

        $userRepository->insert('Bob', 'bob@example.com', Hash::make('x'));
        $row = $userRepository->findByEmail('bob@example.com');

        $this->assertInstanceOf(User::class, $row);
        $this->assertSame('Bob', $row->name);
    }

    public function test_update_remember_token(): void
    {
        $userRepository = app(UserRepository::class);

        $id = $userRepository->insert('Carol', 'carol@example.com', Hash::make('x'));
        $userRepository->updateRememberToken($id, 'token-xyz');

        $row = $userRepository->findById($id);

        $this->assertSame('token-xyz', $row->rememberToken);
    }
}
