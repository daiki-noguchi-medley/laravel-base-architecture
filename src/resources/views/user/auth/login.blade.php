@extends('layouts.user')

@section('title', 'ログイン')

@section('content')
<div class="card" x-data="{ showPassword: false }">
    <h1>ユーザーログイン</h1>

    <form action="{{ route('user.login') }}" method="POST">
        @csrf

        <div class="form-group">
            <label for="email">メールアドレス</label>
            <input type="email"
                   name="email"
                   id="email"
                   value="{{ old('email') }}"
                   required autofocus autocomplete="email">
            @error('email')
                <div class="error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="password">パスワード</label>
            <input :type="showPassword ? 'text' : 'password'"
                   name="password"
                   id="password"
                   required autocomplete="current-password">
            <label class="checkbox" style="margin-top: .25rem;">
                <input type="checkbox" x-model="showPassword">
                <span x-text="showPassword ? '隠す' : '表示する'"></span>
            </label>
            @error('password')
                <div class="error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="checkbox">
                <input type="checkbox" name="remember" value="1">
                ログイン状態を保持
            </label>
        </div>

        <button type="submit" class="primary">ログイン</button>
    </form>
</div>
@endsection
