<?php

declare(strict_types=1);

use App\Http\Controller\Admin\Auth\LoginController as AdminLoginController;
use App\Http\Controller\Admin\DashboardController as AdminDashboardController;
use App\Http\Controller\Admin\UserManagement\UserManagementController;
use Illuminate\Support\Facades\Route;

// =============================================================================
// Admin (管理者: Vite + React + Bootstrap + FontAwesome)
//   bootstrap/app.php の `then` クロージャから `web` middleware で読み込まれる
// =============================================================================
Route::middleware('guest:admin')->group(function () {
    Route::get('/admin/login',  [AdminLoginController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/admin/login', [AdminLoginController::class, 'login']);
});

Route::middleware('auth:admin')->group(function () {
    Route::post('/admin/logout', [AdminLoginController::class, 'logout'])->name('admin.logout');

    // SPA から呼ばれる JSON API (admin/api/* 配下に集約)。
    // /admin{any?} の catch-all より前に定義する必要あり (順序重要)
    Route::prefix('admin/api')->group(function () {
        Route::get('/users',          [UserManagementController::class, 'index'])->name('admin.api.users.index');
        Route::post('/users',         [UserManagementController::class, 'store'])->name('admin.api.users.store');
        Route::delete('/users/{id}',  [UserManagementController::class, 'destroy'])
            ->whereNumber('id')
            ->name('admin.api.users.destroy');
    });

    // /admin および /admin/任意パス は React SPA をマウントする (router は React 側)
    Route::get('/admin{any?}', [AdminDashboardController::class, 'index'])
        ->where('any', '.*')
        ->name('admin.dashboard');
});
