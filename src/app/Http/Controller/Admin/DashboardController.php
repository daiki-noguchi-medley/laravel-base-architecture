<?php

declare(strict_types=1);

namespace App\Http\Controller\Admin;

use App\Auth\Admin\AdminAuth;
use App\Http\Controller\Controller;
use App\Http\ViewModel\Admin\AdminAppViewModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

final class DashboardController extends Controller
{
    /**
     * React SPA のマウントポイント。
     * 認証チェックは route middleware (auth:admin) で行う。
     * 実際の画面は resources/js/admin から render される。
     */
    public function index(): View
    {
        $auth = Auth::guard('admin')->user();
        if (! $auth instanceof AdminAuth) {
            // auth:admin ミドルウェアを通っているので通常ここには来ない (型保証用)
            throw new RuntimeException('admin guard が AdminAuth を返しませんでした');
        }

        return view('admin.app', [
            'vm' => AdminAppViewModel::fromAuth($auth),
        ]);
    }
}
