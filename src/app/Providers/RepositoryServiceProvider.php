<?php

declare(strict_types=1);

namespace App\Providers;

use Demo\Admin\Repository\AdminRepository;
use Demo\Admin\Repository\AdminRepositoryImpl;
use Demo\Kanban\Repository\KanbanCardRepository;
use Demo\Kanban\Repository\KanbanCardRepositoryImpl;
use Demo\User\Repository\UserRepository;
use Demo\User\Repository\UserRepositoryImpl;
use Illuminate\Support\ServiceProvider;

/**
 * Repository の interface → 実装 (Impl) の DI バインドを集約する Provider。
 *
 * 新しい Repository を `Demo/<ドメイン>/Repository/` に追加したら、ここで
 * `$this->app->bind(XxxRepository::class, XxxRepositoryImpl::class)` を追記する。
 *
 * (Service の binding は ServiceServiceProvider、Auth::provider() の登録は
 *  AppServiceProvider が担当)
 */
final class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // --- User ---
        $this->app->bind(UserRepository::class, UserRepositoryImpl::class);

        // --- Admin ---
        $this->app->bind(AdminRepository::class, AdminRepositoryImpl::class);

        // --- Kanban ---
        $this->app->bind(KanbanCardRepository::class, KanbanCardRepositoryImpl::class);
    }
}
