<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\Auth\LoginController as AdminLoginController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
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

    // /admin および /admin/任意パス は React SPA をマウントする (router は React 側)
    Route::get('/admin{any?}', [AdminDashboardController::class, 'index'])
        ->where('any', '.*')
        ->name('admin.dashboard');
});
