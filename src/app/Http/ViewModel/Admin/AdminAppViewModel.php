<?php

declare(strict_types=1);

namespace App\Http\ViewModel\Admin;

use App\Auth\Admin\AdminAuth;

/**
 * admin/app.blade.php (React SPA のシェル) に渡す ViewModel。
 *
 * ログイン画面もダッシュボードも同じシェルを返すので、**未認証でも成り立つ形**にする
 * (認証済みのときだけ管理者の情報が入り、SPA へ window.__adminUser として渡される)。
 *
 * Blade 側で `auth('admin')->user()` を呼ばないための入れ物。
 * view にロジックを置かない (CLAUDE.md §8) ので、詰め替えは Controller 側で行う。
 */
final readonly class AdminAppViewModel
{
    public function __construct(
        public ?int $adminId,
        public ?string $adminName,
        public ?string $adminEmail,
    ) {}

    /**
     * 認証中の AdminAuth から組み立てる。
     */
    public static function fromAuth(AdminAuth $auth): self
    {
        return new self(
            adminId: $auth->id(),
            adminName: $auth->name(),
            adminEmail: $auth->email(),
        );
    }

    /**
     * 未認証 (ログイン画面) 用。管理者の情報を SPA へ渡さない。
     */
    public static function guest(): self
    {
        return new self(adminId: null, adminName: null, adminEmail: null);
    }

    /**
     * 管理者としてログイン済みか (SPA へ管理者情報を渡すかどうかの判定)。
     */
    public function isAuthenticated(): bool
    {
        return $this->adminId !== null;
    }
}
