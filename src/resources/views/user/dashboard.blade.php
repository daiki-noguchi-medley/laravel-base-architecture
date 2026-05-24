@extends('layouts.user')

@section('title', 'ダッシュボード')

@section('content')
<div class="card">
    <h1>ようこそ、{{ $vm->userName }}さん</h1>
    <p>
        <strong>Email:</strong> {{ $vm->userEmail }}<br>
        <strong>User ID:</strong> {{ $vm->userId }}
    </p>

    <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid #eee;">

    <h2>Kanban ボード</h2>
    <p>カードをドラッグして 4 レーン (TODO/DOING/REVIEW/DONE) に振り分けるサンプル。</p>
    <a href="{{ route('user.kanban') }}" class="primary" style="display: inline-block; padding: .5rem 1.5rem; background: #2563eb; color: white; border: none; border-radius: 4px; text-decoration: none;">
        Kanban を開く
    </a>

    <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid #eee;">

    {{-- htmx demo: ボタンクリックで /api/server-time を fetch して innerHTML を入れ替える --}}
    <h2>htmx デモ — サーバー時刻取得</h2>
    <button class="primary"
            hx-get="{{ url('/api/server-time') }}"
            hx-target="#server-time"
            hx-swap="innerHTML">
        サーバー時刻を取得
    </button>
    <div id="server-time" style="margin-top: 1rem; padding: .75rem; background: #f0f0f0; border-radius: 4px; font-family: ui-monospace, monospace;">
        (ボタンを押すと JST 時刻が表示されます)
    </div>

    <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid #eee;">

    {{-- Alpine.js demo: ローカル state のカウンタ --}}
    <div x-data="{ count: 0 }">
        <h2>Alpine.js デモ — カウンタ</h2>
        <p>現在のカウント: <strong x-text="count"></strong></p>
        <button class="primary" @click="count++">+1</button>
        <button class="primary" @click="count = 0" style="background: #6b7280; margin-left: .5rem;">リセット</button>
    </div>
</div>
@endsection
