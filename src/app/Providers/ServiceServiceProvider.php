<?php

declare(strict_types=1);

namespace App\Providers;

use Demo\Admin\Service\AdminAuthService;
use Demo\Admin\Service\AdminAuthServiceImpl;
use Demo\Admin\Service\UserManagementService;
use Demo\Admin\Service\UserManagementServiceImpl;
use Demo\Kanban\Service\KanbanService;
use Demo\Kanban\Service\KanbanServiceImpl;
use Demo\User\Service\UserAuthService;
use Demo\User\Service\UserAuthServiceImpl;
use Illuminate\Support\ServiceProvider;

/**
 * Service の interface → 実装 (Impl) の DI バインドを集約する Provider。
 *
 * 新しい Service を `Demo/<ドメイン>/Service/` に追加したら、ここで
 * `$this->app->bind(XxxService::class, XxxServiceImpl::class)` を追記する。
 *
 * (Repository の binding は RepositoryServiceProvider、Auth::provider() の登録は
 *  AppServiceProvider が担当)
 */
final class ServiceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // --- User ---
        $this->app->bind(UserAuthService::class, UserAuthServiceImpl::class);

        // --- Admin ---
        $this->app->bind(AdminAuthService::class, AdminAuthServiceImpl::class);
        $this->app->bind(UserManagementService::class, UserManagementServiceImpl::class);

        // --- Kanban ---
        $this->app->bind(KanbanService::class, KanbanServiceImpl::class);
    }
}
