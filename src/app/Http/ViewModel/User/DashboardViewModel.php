<?php

declare(strict_types=1);

namespace App\Http\ViewModel\User;

use App\Auth\User\UserAuth;

/**
 * user/dashboard.blade.php に渡す ViewModel。
 *
 * Blade からは $vm->userName 等で参照する。
 * Auth (UserAuth) などの依存型を Blade に直接渡さず、ここで詰め替えることで
 * View が必要なデータだけを受け取るようにする (View 層の責務を最小化)。
 */
final readonly class DashboardViewModel
{
    public function __construct(
        public int $userId,
        public string $userName,
        public string $userEmail,
    ) {}

    /**
     * 認証中の UserAuth から ViewModel を組み立てる。
     */
    public static function fromAuth(UserAuth $auth): self
    {
        return new self(
            userId: $auth->id(),
            userName: $auth->name(),
            userEmail: $auth->email(),
        );
    }
}
