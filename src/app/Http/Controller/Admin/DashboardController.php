<?php

declare(strict_types=1);

namespace App\Http\Controller\Admin;

use App\Http\Controller\Controller;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    /**
     * React SPA のマウントポイント。
     * 認証チェックは route middleware (auth:admin) で行う。
     * 実際の画面は resources/js/admin から render される。
     */
    public function index(): View
    {
        return view('admin.app');
    }
}
