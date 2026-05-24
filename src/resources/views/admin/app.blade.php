<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面 | Laravel Base Architecture</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- favicon --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">

    @vite(['resources/js/admin/app.tsx'])
</head>
<body>
    <div id="admin-app"></div>

    {{-- 認証済みなら admin 情報を React に注入 --}}
    @auth('admin')
        @php($admin = auth('admin')->user())
        <script>
            window.__adminUser = {
                id: {{ $admin->id() }},
                name: @json($admin->name()),
                email: @json($admin->email())
            };
        </script>
    @endauth

    {{-- FormRequest のバリデーションエラーを React に注入 --}}
    @if ($errors->any())
        <script>
            window.__pageErrors = @json($errors->messages());
        </script>
    @endif

    {{-- old('email') の復元値 --}}
    @if (old('email'))
        <script>
            window.__oldEmail = @json(old('email'));
        </script>
    @endif
</body>
</html>
