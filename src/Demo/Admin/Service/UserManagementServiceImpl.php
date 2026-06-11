<?php

declare(strict_types=1);

namespace Demo\Admin\Service;

use Demo\User\Repository\UserRepository;
use App\Model\User\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class UserManagementServiceImpl implements UserManagementService
{
    /**
     * 自動生成するパスワードの長さ。
     * 英大小数字記号混在で 16 文字 → 十分な強度。
     */
    private const int GENERATED_PASSWORD_LENGTH = 16;

    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}

    public function getUserList(): array
    {
        return $this->userRepository->getUserList();
    }

    public function createUser(string $name, string $email): array
    {
        $plainPassword = $this->generateRandomPassword();
        $userId        = $this->userRepository->insert(
            name: $name,
            email: $email,
            hashedPassword: Hash::make($plainPassword),
        );

        $user = $this->userRepository->findById($userId)
            ?? throw new \RuntimeException("created user not found: {$userId}");

        return [
            'user'          => $user,
            'plainPassword' => $plainPassword,
        ];
    }

    public function deleteUser(int $userId): void
    {
        $this->mustGetUser($userId);
        $this->userRepository->softDelete($userId);
    }

    /**
     * ユーザーを取得しつつ、存在しなければ InvalidArgumentException で弾く。
     *
     * @param int $userId 対象ユーザー ID
     * @return User 存在するユーザー
     * @throws InvalidArgumentException 未登録 / 論理削除済み
     */
    private function mustGetUser(int $userId): User
    {
        return $this->userRepository->findById($userId)
            ?? throw new InvalidArgumentException("user not found: {$userId}");
    }

    /**
     * 英数字混在のランダムパスワードを生成する。
     * Str::password は記号も含むがコピペ事故が多いので、ここでは英数字のみで 16 文字。
     *
     * @return string 生成されたパスワード平文 (Str::random で英数字 16 桁)
     */
    private function generateRandomPassword(): string
    {
        return Str::random(self::GENERATED_PASSWORD_LENGTH);
    }
}
