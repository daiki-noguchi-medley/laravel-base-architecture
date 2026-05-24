<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function index(): View
    {
        return view('user.dashboard', [
            'user' => Auth::guard('user')->user(),
        ]);
    }
}
