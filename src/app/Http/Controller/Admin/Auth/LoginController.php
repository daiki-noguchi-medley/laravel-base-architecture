<?php

declare(strict_types=1);

namespace App\Http\Controller\Admin\Auth;

use App\Http\Controller\Controller;
use App\Http\Request\Admin\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        // React SPA のマウントポイント (admin.app blade を返す)
        return view('admin.app');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = [
            'email'    => $request->validated(LoginRequest::EMAIL),
            'password' => $request->validated(LoginRequest::PASSWORD),
        ];
        $remember = (bool) $request->validated(LoginRequest::REMEMBER);

        if (! Auth::guard('admin')->attempt($credentials, $remember)) {
            return back()
                ->withErrors([LoginRequest::EMAIL => 'メールアドレスまたはパスワードが間違っています。'])
                ->onlyInput(LoginRequest::EMAIL);
        }

        $request->session()->regenerate();
        return redirect()->intended('/admin');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/admin/login');
    }
}
