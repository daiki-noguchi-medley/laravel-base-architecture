# htmx + Alpine.js (ユーザー画面)

ユーザー画面 (`/login`, `/dashboard` 等の Blade ページ) は **htmx + Alpine.js** で動的化している。
管理画面の React と違って、SSR (Blade) + 部分更新 / 軽量 reactive で **JS 量を最小** に保つ方針。

## 役割分担

| ライブラリ | 役割 | 1 行で言うと |
|---|---|---|
| **htmx** | サーバー通信 (XHR) を **HTML 属性** で書く | `<button hx-get="/...">` でクリック → サーバーから HTML 取得 → DOM 置換 |
| **Alpine.js** | クライアント側の **軽量 reactive** (state / event) | `<div x-data="{count:0}"><button @click="count++">` |

両方とも npm + Vite でローカルバンドル ([`resources/js/user.js`](../src/resources/js/user.js))。CDN 不使用。

```js
// resources/js/user.js
import 'htmx.org';
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();
```

Blade では `@vite(['resources/js/user.js'])` で読み込む (`resources/views/layouts/user.blade.php`)。

---

## htmx クイックリファレンス

### 基本: サーバーから HTML を取って置換

```html
<button hx-get="/api/server-time"
        hx-target="#result"
        hx-swap="innerHTML">
  取得
</button>
<div id="result">(ここに入る)</div>
```

| 属性 | 役割 | 例 |
|---|---|---|
| `hx-get` / `hx-post` / `hx-put` / `hx-delete` | HTTP メソッド + URL | `hx-post="/comments"` |
| `hx-target` | 結果を挿入する要素 (CSS セレクタ) | `hx-target="#result"`, `hx-target="closest tr"` |
| `hx-swap` | 挿入方法 | `innerHTML` (default) / `outerHTML` / `beforeend` / `afterbegin` / `none` / `delete` |
| `hx-trigger` | 発火イベント (default は要素種別ごとに最適化) | `hx-trigger="change"`, `hx-trigger="every 5s"`, `hx-trigger="load"` |
| `hx-vals` | 送信パラメータ (JSON) | `hx-vals='{"foo": "bar"}'` |
| `hx-headers` | リクエストヘッダ (JSON) | `hx-headers='{"X-Custom": "..."}'` |
| `hx-confirm` | 送信前に confirm ダイアログ | `hx-confirm="削除しますか?"` |
| `hx-indicator` | リクエスト中に表示する要素 | `hx-indicator="#spinner"` |
| `hx-include` | 一緒に送る入力欄 | `hx-include="[name='csrf_token']"` |

### Laravel CSRF token を付ける (POST/PUT/DELETE 必須)

3 通り。プロジェクトでは **(a)** を推奨。

**(a) `<meta name="csrf-token">` から htmx 自動付与** (推奨):

```html
{{-- layouts/user.blade.php に既にある --}}
<meta name="csrf-token" content="{{ csrf_token() }}">

{{-- どこか共通 init (user.js でやってもよい) --}}
<body hx-headers='js:{"X-CSRF-TOKEN": document.querySelector(\'meta[name="csrf-token"]\').content}'>
```

**(b) 個別要素に hx-headers**:

```html
<button hx-post="/like"
        hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'>
  Like
</button>
```

**(c) form の hidden field (`@csrf`)**: 通常の POST フォームを htmx 経由で送るときに `@csrf` を含めれば OK。

---

## Alpine.js クイックリファレンス

### 基本: ローカル state + イベント

```html
<div x-data="{ count: 0 }">
  <p>カウント: <span x-text="count"></span></p>
  <button @click="count++">+1</button>
  <button @click="count = 0">reset</button>
</div>
```

| ディレクティブ | 役割 | 例 |
|---|---|---|
| `x-data` | スコープ + 初期 state (JS オブジェクト) | `x-data="{ open: false, list: [] }"` |
| `x-text` | textContent に値を反映 | `<span x-text="count"></span>` |
| `x-html` | innerHTML (XSS 注意、極力避ける) | `x-html="rawHtml"` |
| `x-show` | 真偽で表示切替 (CSS `display`) | `x-show="open"` |
| `x-if` | 条件で DOM 自体を追加/削除 (`<template>` 必須) | `<template x-if="user"><p x-text="user.name"></p></template>` |
| `x-for` | ループ (`<template>` 必須) | `<template x-for="item in list" :key="item.id">` |
| `x-model` | 双方向バインディング (input/select) | `<input x-model="search">` |
| `@click` / `x-on:click` | イベントハンドラ | `@click="open = !open"`, `@click.prevent="..."` |
| `:class` / `x-bind:class` | 属性バインディング | `:class="{ 'is-open': open }"` |
| `x-init` | 初期化処理 (mount 時) | `x-init="$watch('search', val => ...)"` |
| `x-ref` + `$refs` | DOM 要素参照 | `<input x-ref="email">` → `$refs.email.focus()` |
| `$dispatch` | カスタムイベント発火 | `$dispatch('user-updated', { id: 1 })` |

### 修飾子 (event modifier)

```html
<form @submit.prevent="save()">        {{-- preventDefault --}}
<input @keydown.enter="search()">       {{-- 特定キー --}}
<button @click.once="boot()">           {{-- 1 回だけ --}}
<a @click.stop="...">                   {{-- stopPropagation --}}
```

### ストア (グローバル state、複数コンポーネントで共有)

```js
// user.js
Alpine.store('cart', {
    items: [],
    add(item) { this.items.push(item); },
});
```

```html
<span x-text="$store.cart.items.length"></span>
<button @click="$store.cart.add({id:1})">追加</button>
```

---

## htmx × Alpine.js の組み合わせ

得意分野が違うので併用が自然:

- **htmx**: 「サーバーから最新の HTML を取って差し替える」
- **Alpine.js**: 「クライアントで開閉する、件数を数える、入力中の値を保持する」

### 例: 検索フォーム

```html
<div x-data="{ q: '' }">
  <input x-model="q"
         placeholder="検索"
         hx-get="/search"
         hx-trigger="keyup changed delay:300ms"
         hx-target="#results"
         hx-include="this">

  <p x-show="q.length > 0">「<span x-text="q"></span>」で検索中…</p>

  <div id="results"></div>
</div>
```

- Alpine で入力値 `q` を保持 (表示用)
- htmx で 300ms debounce 後にサーバー検索 → `#results` に HTML を挿入

### 例: 削除確認 + 行除去

```html
<tr id="row-1">
  <td>foo</td>
  <td>
    <button hx-delete="/items/1"
            hx-confirm="削除しますか?"
            hx-target="closest tr"
            hx-swap="outerHTML swap:300ms"
            hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'>
      削除
    </button>
  </td>
</tr>
```

サーバーは空レスポンス (`return response()->noContent();`) でよい。
`hx-swap="outerHTML swap:300ms"` で row 全体が 300ms かけて消える。

---

## このプロジェクトでの実例

### `resources/views/user/auth/login.blade.php` (Alpine だけ)

```blade
<div class="card" x-data="{ showPassword: false }">
  <input :type="showPassword ? 'text' : 'password'"
         name="password"
         id="password" required>
  <label class="checkbox">
    <input type="checkbox" x-model="showPassword">
    <span x-text="showPassword ? '隠す' : '表示する'"></span>
  </label>
</div>
```

→ Alpine ローカル state `showPassword` で input の type を切り替え + ラベル文言も切替。

### `resources/views/user/dashboard.blade.php` (htmx + Alpine 両方)

```blade
{{-- htmx: ボタン → サーバー時刻取得 --}}
<button hx-get="{{ url('/api/server-time') }}"
        hx-target="#server-time"
        hx-swap="innerHTML">
  サーバー時刻を取得
</button>
<div id="server-time">(ボタンを押してください)</div>

{{-- Alpine: ローカルカウンタ --}}
<div x-data="{ count: 0 }">
  <p>現在のカウント: <strong x-text="count"></strong></p>
  <button @click="count++">+1</button>
  <button @click="count = 0">リセット</button>
</div>
```

---

## ハマりどころ

| 症状 | 原因 | 対処 |
|---|---|---|
| `Alpine is not defined` | `Alpine.start()` を呼んでいない | `user.js` で `Alpine.start()` を呼ぶ (既に対応済み) |
| POST で 419 (Page Expired) | CSRF token 不足 | `hx-headers` で `X-CSRF-TOKEN` を付ける or form に `@csrf` |
| Alpine が動かない (CDN から ESM の `import`) | CDN 版だと grobal、Vite 版は `window.Alpine = Alpine; Alpine.start()` 必須 | `user.js` の通り window 注入 + start() |
| `x-for` / `x-if` が表示されない | `<template>` で囲んでいない | `<template x-for="...">` / `<template x-if="...">` |
| htmx で返す HTML 内の Alpine が動かない | htmx は HTML 挿入時に Alpine を初期化しないことがある | `htmx:afterSwap` で `Alpine.initTree(event.detail.target)` を呼ぶ |
| `@click.prevent` 等の修飾子が効かない | typo (`@click.prvent` 等) | docs.alpinejs.com で修飾子一覧確認 |

### htmx で受け取った HTML 内に Alpine がある場合の初期化

```js
// user.js に追記する例
document.addEventListener('htmx:afterSwap', (event) => {
    if (window.Alpine && event.detail.target) {
        window.Alpine.initTree(event.detail.target);
    }
});
```

---

## 公式リソース

- [htmx 公式](https://htmx.org/) — `htmx.org/docs/` の Reference が早い
- [Alpine.js 公式](https://alpinejs.dev/) — Directives / Magics / Plugins
- [htmx + Alpine.js patterns](https://htmx.org/essays/alpine.html) — htmx 公式の併用ガイド
- [HTMX + Hyperscript vs Alpine](https://htmx.org/essays/hypermedia-friendly-scripting/) — 思想理解
