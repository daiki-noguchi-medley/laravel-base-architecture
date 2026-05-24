<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 未認証時のリダイレクト先をパスに応じて振り分ける
        //   /admin/* → admin.login
        //   それ以外 → user.login
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('admin/*')
                ? route('admin.login')
                : route('user.login')
        );

        // 認証済みユーザーが guest middleware 配下にアクセスしたときのリダイレクト先
        $middleware->redirectUsersTo(
            fn (Request $request) => $request->is('admin/*')
                ? '/admin'
                : '/dashboard'
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
