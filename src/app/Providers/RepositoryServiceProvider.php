<?php

declare(strict_types=1);

namespace App\Providers;

use Demo\Repository\Admin\AdminRepository;
use Demo\Repository\Admin\AdminRepositoryImpl;
use Demo\Repository\User\UserRepository;
use Demo\Repository\User\UserRepositoryImpl;
use Illuminate\Support\ServiceProvider;

/**
 * Repository の interface → 実装 (Impl) の DI バインドを集約する Provider。
 *
 * 新しい Repository を `Demo/Repository/<Logic>/` に追加したら、ここで
 * `$this->app->bind(XxxRepository::class, XxxRepositoryImpl::class)` を追記する。
 *
 * (Service の binding は ServiceServiceProvider、Auth::provider() の登録は
 *  AppServiceProvider が担当)
 */
class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // --- User ---
        $this->app->bind(UserRepository::class, UserRepositoryImpl::class);

        // --- Admin ---
        $this->app->bind(AdminRepository::class, AdminRepositoryImpl::class);
    }
}
