<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\Auth\LoginController as AdminLoginController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\User\Auth\LoginController as UserLoginController;
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// =============================================================================
// User (一般ユーザー: Blade + htmx + Alpine.js)
// =============================================================================
Route::middleware('guest:user')->group(function () {
    Route::get('/login',  [UserLoginController::class, 'showLoginForm'])->name('user.login');
    Route::post('/login', [UserLoginController::class, 'login']);
});

Route::middleware('auth:user')->group(function () {
    Route::get('/dashboard',  [UserDashboardController::class, 'index'])->name('user.dashboard');
    Route::post('/logout',    [UserLoginController::class, 'logout'])->name('user.logout');

    // htmx デモ用エンドポイント (Blade dashboard から呼び出し)
    Route::get('/api/server-time', fn () => now()->format('Y-m-d H:i:s T'));
});

// =============================================================================
// Admin (管理者: Vite + React + Bootstrap + FontAwesome)
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
