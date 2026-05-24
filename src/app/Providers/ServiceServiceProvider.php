<?php

declare(strict_types=1);

namespace App\Providers;

use Demo\Service\Admin\AdminAuthService;
use Demo\Service\Admin\AdminAuthServiceImpl;
use Demo\Service\Admin\UserManagementService;
use Demo\Service\Admin\UserManagementServiceImpl;
use Demo\Service\Kanban\KanbanService;
use Demo\Service\Kanban\KanbanServiceImpl;
use Demo\Service\User\UserAuthService;
use Demo\Service\User\UserAuthServiceImpl;
use Illuminate\Support\ServiceProvider;

/**
 * Service の interface → 実装 (Impl) の DI バインドを集約する Provider。
 *
 * 新しい Service を `Demo/Service/<Logic>/` に追加したら、ここで
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
