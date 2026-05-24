<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        return view('user.auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = [
            'email'    => $request->validated(LoginRequest::EMAIL),
            'password' => $request->validated(LoginRequest::PASSWORD),
        ];
        $remember = (bool) $request->validated(LoginRequest::REMEMBER);

        if (! Auth::guard('user')->attempt($credentials, $remember)) {
            return back()
                ->withErrors([LoginRequest::EMAIL => 'メールアドレスまたはパスワードが間違っています。'])
                ->onlyInput(LoginRequest::EMAIL);
        }

        $request->session()->regenerate();
        return redirect()->intended('/dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('user')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
