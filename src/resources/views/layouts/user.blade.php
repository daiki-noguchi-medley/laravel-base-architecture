<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'User') | Laravel Base Architecture</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- htmx (CDN) — XHR を HTML 属性で書ける軽量ライブラリ --}}
    <script src="https://unpkg.com/htmx.org@2.0.4" defer></script>

    {{-- Alpine.js (CDN) — 軽量 reactive (props / state を HTML 属性で) --}}
    <script src="https://unpkg.com/alpinejs@3.x.x" defer></script>

    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", "Hiragino Sans", sans-serif; max-width: 720px; margin: 2rem auto; padding: 0 1rem; color: #1a1a1a; }
        nav { margin-bottom: 1.5rem; padding-bottom: .75rem; border-bottom: 1px solid #ddd; }
        nav a, nav button { color: #2563eb; text-decoration: none; }
        nav a:hover, nav button:hover { text-decoration: underline; }
        .card { padding: 1.5rem; border: 1px solid #ddd; border-radius: 8px; background: #fff; }
        h1 { margin-top: 0; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: .25rem; font-weight: 600; }
        label.checkbox { font-weight: normal; display: inline-block; margin-right: 1rem; }
        input[type=email], input[type=password], input[type=text] {
            width: 100%; padding: .5rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;
        }
        .error { color: #c00; font-size: .875rem; margin-top: .25rem; }
        button.primary { padding: .5rem 1.5rem; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
        button.primary:hover { background: #1d4ed8; }
        button.link { background: none; color: #2563eb; padding: 0; border: none; text-decoration: underline; cursor: pointer; font: inherit; }
    </style>
</head>
<body>
    <nav>
        <a href="/">トップ</a>
        @auth('user')
            <span style="margin: 0 .5rem;">|</span>
            <a href="{{ route('user.dashboard') }}">ダッシュボード</a>
            <span style="margin: 0 .5rem;">|</span>
            <form action="{{ route('user.logout') }}" method="POST" style="display: inline;">
                @csrf
                <button type="submit" class="link">ログアウト</button>
            </form>
        @else
            <span style="margin: 0 .5rem;">|</span>
            <a href="{{ route('user.login') }}">ログイン</a>
        @endauth
    </nav>

    @yield('content')
</body>
</html>
