<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\Admin\AdminAuthProvider;
use App\Auth\User\UserAuthProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

/**
 * アプリケーション全体の boot 処理を担当する Provider。
 *
 * 役割分担:
 *   - Service の interface → Impl バインド   : ServiceServiceProvider
 *   - Repository の interface → Impl バインド : RepositoryServiceProvider
 *   - Auth::provider() 等の Laravel boot     : ここ (AppServiceProvider)
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // config/auth.php の providers.user.driver と一致
        Auth::provider('userauth', fn ($app) => $app->make(UserAuthProvider::class));

        // config/auth.php の providers.admin.driver と一致
        Auth::provider('adminauth', fn ($app) => $app->make(AdminAuthProvider::class));
    }
}
