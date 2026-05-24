<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kanban | Laravel Base Architecture</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">

    @vite(['resources/js/kanban.js'])

    <style>
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, "Segoe UI", "Hiragino Sans", sans-serif;
            margin: 0; padding: 0; background: #f3f4f6; color: #1a1a1a;
        }
        .kanban-header {
            background: #1f2937; color: #fff; padding: 1rem 1.5rem;
            display: flex; justify-content: space-between; align-items: center;
        }
        .kanban-header a, .kanban-header button { color: #93c5fd; background: none; border: none; cursor: pointer; }
        .kanban-board {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;
            padding: 1.5rem; min-height: calc(100vh - 64px);
        }
        .kanban-lane {
            background: #e5e7eb; border-radius: 8px; padding: .75rem; display: flex; flex-direction: column;
        }
        .kanban-lane h3 {
            margin: 0 0 .5rem; font-size: 1rem; padding: .25rem .5rem; border-radius: 4px; color: #fff;
        }
        .kanban-lane[data-lane-key="todo"]   h3 { background: #6b7280; }
        .kanban-lane[data-lane-key="doing"]  h3 { background: #2563eb; }
        .kanban-lane[data-lane-key="review"] h3 { background: #f59e0b; }
        .kanban-lane[data-lane-key="done"]   h3 { background: #16a34a; }

        .kanban-lane-body { flex: 1; min-height: 100px; }
        .kanban-card {
            background: #fff; border-radius: 6px; padding: .75rem; margin-bottom: .5rem;
            box-shadow: 0 1px 2px rgba(0,0,0,.06); cursor: grab;
        }
        .kanban-card:active { cursor: grabbing; }
        .kanban-card h4 { margin: 0 0 .25rem; font-size: .95rem; }
        .kanban-card p { margin: 0; color: #4b5563; font-size: .8rem; white-space: pre-wrap; }
        .kanban-card-ghost { opacity: .4; }
        .kanban-card-actions { display: flex; gap: .25rem; margin-top: .5rem; }
        .kanban-card-actions button {
            font-size: .75rem; padding: .15rem .5rem; background: #f3f4f6; border: 1px solid #d1d5db;
            border-radius: 4px; cursor: pointer;
        }
        .kanban-card-actions button:hover { background: #e5e7eb; }
        .kanban-add {
            background: #2563eb; color: #fff; border: none; padding: .5rem 1rem;
            border-radius: 6px; cursor: pointer; font-size: .9rem;
        }
        .kanban-add:hover { background: #1d4ed8; }

        /* モーダル */
        .modal-backdrop {
            position: fixed; inset: 0; background: rgba(0,0,0,.5); display: flex;
            align-items: center; justify-content: center; z-index: 1000;
        }
        .modal {
            background: #fff; border-radius: 8px; padding: 1.5rem; width: 90%; max-width: 500px;
        }
        .modal h2 { margin: 0 0 1rem; font-size: 1.1rem; }
        .modal label { display: block; font-weight: 600; margin: .5rem 0 .25rem; }
        .modal input[type=text], .modal textarea {
            width: 100%; padding: .5rem; border: 1px solid #d1d5db; border-radius: 4px;
            font: inherit;
        }
        .modal textarea { min-height: 80px; resize: vertical; }
        .modal-actions { margin-top: 1rem; display: flex; justify-content: flex-end; gap: .5rem; }
        .modal-actions button {
            padding: .5rem 1rem; border: none; border-radius: 4px; cursor: pointer; font-size: .9rem;
        }
        .modal-actions .submit { background: #2563eb; color: #fff; }
        .modal-actions .cancel { background: #e5e7eb; }
        .error-banner {
            background: #fee2e2; color: #991b1b; padding: .5rem 1rem; margin: 0 1.5rem;
            border-radius: 4px;
        }
    </style>
</head>
<body x-data="kanban" x-cloak>
    <div class="kanban-header">
        <div>
            <strong>Kanban ボード</strong>
            <span style="margin: 0 .5rem; opacity: .5;">|</span>
            <a href="{{ route('user.dashboard') }}">ダッシュボードへ戻る</a>
        </div>
        <button type="button" class="kanban-add" @click="openCreate()">+ 新規カード</button>
    </div>

    <template x-if="errorMessage !== null">
        <div class="error-banner" x-text="errorMessage" @click="errorMessage = null" style="cursor: pointer;"></div>
    </template>

    <template x-if="loading">
        <p style="padding: 1.5rem; color: #6b7280;">読み込み中...</p>
    </template>

    <div class="kanban-board" x-show="!loading">
        @foreach ($vm->laneList as $lane)
            <div class="kanban-lane" data-lane-key="{{ $lane->value }}">
                <h3>{{ $lane->label() }}</h3>
                <div class="kanban-lane-body" data-lane="{{ $lane->value }}">
                    <template x-for="card in cardsOfLane('{{ $lane->value }}')" :key="card.id">
                        <div class="kanban-card" :data-card-id="card.id">
                            <h4 x-text="card.title"></h4>
                            <p x-text="card.body"></p>
                            <div class="kanban-card-actions">
                                <button type="button" @click="openEdit(card)">編集</button>
                                <button type="button" @click="deleteCard(card.id)">削除</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        @endforeach
    </div>

    {{-- モーダル (新規 / 編集) --}}
    <template x-if="modalOpen">
        <div class="modal-backdrop" @click.self="closeModal()">
            <div class="modal" @keydown.escape.window="closeModal()">
                <h2 x-text="modalMode === 'create' ? '新規カード作成' : 'カード編集'"></h2>
                <label for="modal-title">タイトル</label>
                <input id="modal-title" type="text" x-model="modalTitle" maxlength="120" autofocus>
                <label for="modal-body">内容</label>
                <textarea id="modal-body" x-model="modalBody" maxlength="2000"></textarea>
                <div class="modal-actions">
                    <button type="button" class="cancel" @click="closeModal()">キャンセル</button>
                    <button type="button" class="submit" @click="submitModal()">
                        <span x-text="modalMode === 'create' ? '作成' : '保存'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <style>[x-cloak] { display: none !important; }</style>
</body>
</html>
