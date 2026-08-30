<?php

declare(strict_types=1);

use App\Http\Controller\User\Auth\LoginController as UserLoginController;
use App\Http\Controller\User\DashboardController as UserDashboardController;
use App\Http\Controller\User\Kanban\KanbanCardController;
use App\Http\Controller\User\Kanban\KanbanPageController;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// =============================================================================
// User (一般ユーザー: Blade + htmx + Alpine.js)
//   /admin/* は routes/admin.php、JSON API は routes/api.php へ
// =============================================================================
Route::middleware('guest:user')->group(function () {
    Route::get('/login',  [UserLoginController::class, 'showLoginForm'])->name('user.login');
    // ⚠ ログインの POST には必ず回数制限を付ける。認証が正しくても、回数制限が無いと
    //   総当たり・使い回しパスワードの試行を止められない。既定の throttle は IP + ルート
    //   単位で数えるので、同じ IP からの連続試行に効く。
    Route::post('/login', [UserLoginController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth:user')->group(function () {
    Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('user.dashboard');
    Route::post('/logout',   [UserLoginController::class, 'logout'])->name('user.logout');

    // htmx デモ用エンドポイント (Blade dashboard から hx-get で呼ばれる、session 必須)
    // → JSON API ではなく session 認証付き user エンドポイントなので web.php に残す
    // ⚠ 裸の now() ヘルパーは使わない (CLAUDE.md §6)
    Route::get('/api/server-time', fn () => CarbonImmutable::now()->format('Y-m-d H:i:s T'));

    // ─── Kanban ボード ──────────────────────────────────────────────
    Route::get('/kanban', [KanbanPageController::class, 'index'])->name('user.kanban');

    // Kanban カード CRUD + 移動 (Blade から fetch で呼ばれる、session + CSRF 必須)
    Route::prefix('kanban/cards')->name('user.kanban.cards.')->group(function () {
        Route::get('/',                 [KanbanCardController::class, 'index'])->name('index');
        Route::post('/',                [KanbanCardController::class, 'store'])->name('store');
        Route::patch('/{id}',           [KanbanCardController::class, 'update'])->whereNumber('id')->name('update');
        Route::patch('/{id}/move',      [KanbanCardController::class, 'move'])->whereNumber('id')->name('move');
        Route::delete('/{id}',          [KanbanCardController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });
});
