<?php

declare(strict_types=1);

namespace App\Http\Controller\User;

use App\Auth\User\UserAuth;
use App\Http\Controller\Controller;
use App\Http\ViewModel\User\DashboardViewModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

final class DashboardController extends Controller
{
    public function index(): View
    {
        $auth = Auth::guard('user')->user();
        if (! $auth instanceof UserAuth) {
            // auth:user ミドルウェアを通っているので通常ここには来ない (型保証用)
            throw new RuntimeException('user guard が UserAuth を返しませんでした');
        }

        return view('user.dashboard', [
            'vm' => DashboardViewModel::fromAuth($auth),
        ]);
    }
}
