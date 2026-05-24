<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\Admin\AdminAuthProvider;
use App\Auth\User\UserAuthProvider;
use Demo\Repository\Admin\AdminRepository;
use Demo\Repository\Admin\AdminRepositoryImpl;
use Demo\Repository\User\UserRepository;
use Demo\Repository\User\UserRepositoryImpl;
use Demo\Service\Admin\AdminAuthService;
use Demo\Service\Admin\AdminAuthServiceImpl;
use Demo\Service\User\UserAuthService;
use Demo\Service\User\UserAuthServiceImpl;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // --- User 認証層 ---
        $this->app->bind(UserRepository::class, UserRepositoryImpl::class);
        $this->app->bind(UserAuthService::class, UserAuthServiceImpl::class);

        // --- Admin 認証層 ---
        $this->app->bind(AdminRepository::class, AdminRepositoryImpl::class);
        $this->app->bind(AdminAuthService::class, AdminAuthServiceImpl::class);
    }

    public function boot(): void
    {
        // config/auth.php の providers.user.driver と一致
        Auth::provider('userauth', fn ($app) => $app->make(UserAuthProvider::class));

        // config/auth.php の providers.admin.driver と一致
        Auth::provider('adminauth', fn ($app) => $app->make(AdminAuthProvider::class));
    }
}
