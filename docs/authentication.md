# Laravel 認証

このプロジェクトの認証実装と、他の認証方式との比較。

- 規約 (Service/Repository/UserProvider 等): [`../CLAUDE.md §4`](../CLAUDE.md)
- アプリ内のクラス構成: [`../src/README.md`](../src/README.md)

---

## このプロジェクトの方式 — **Session ベース + 自前 `UserProvider`**

### 全体像

```
[Browser] ─ POST /login (email + password + _token) ─→
       │
       ▼
[Laravel auth middleware]   ← session cookie あり/なし
       │
       ▼
[LoginController@login]
   $request->validated(LoginRequest::EMAIL/PASSWORD)
       ↓
   Auth::guard('user')->attempt($credentials)
       │
       ▼
[UserAuthProvider]   (App\Auth\User\UserAuthProvider)
   ├─ retrieveByCredentials → UserRepository::findByEmail
   └─ validateCredentials   → password_verify
       │ 認証 OK
       ▼
[UserAuth (Authenticatable)]   ←  session に user_id を保存
       │
       ▼
   redirect /dashboard
```

### なぜ Eloquent ではなく自前 `UserProvider` か

- **Eloquent モデルを Service 層に出さない** (CLAUDE.md §4 鉄則)
- 認証 UI / Auth ファサードからは `UserAuth` (Authenticatable 実装) を返す
- `UserAuth` は内部で `User` Model (`App\Model\User\User`、Repository が返す readonly オブジェクト) を保持
- Repository は `DB::table('user')` のクエリビルダー基本

これにより、Service 層 / Controller 層は **Eloquent に依存しない** 純粋な PHP オブジェクトのみ扱う。

### 2 ガード分離 (user / admin)

`config/auth.php`:

```php
'guards' => [
    'user'  => ['driver' => 'session', 'provider' => 'user'],
    'admin' => ['driver' => 'session', 'provider' => 'admin'],
],

'providers' => [
    'user'  => ['driver' => 'userauth'],    // ↓ で登録するカスタム driver
    'admin' => ['driver' => 'adminauth'],
],
```

`AppServiceProvider::boot()`:

```php
Auth::provider('userauth',  fn ($app) => $app->make(UserAuthProvider::class));
Auth::provider('adminauth', fn ($app) => $app->make(AdminAuthProvider::class));
```

ルートで使い分け (`routes/web.php` / `routes/admin.php`):

```php
Route::middleware('guest:user')->group(/* /login */);
Route::middleware('auth:user')->group(/* /dashboard */);

Route::middleware('guest:admin')->group(/* /admin/login */);
Route::middleware('auth:admin')->group(/* /admin/* */);
```

未認証時のリダイレクト先は `bootstrap/app.php` で **パス別に振り分け**:

```php
$middleware->redirectGuestsTo(
    fn ($request) => $request->is('admin', 'admin/*')
        ? route('admin.login')   // /admin/* → admin.login
        : route('user.login')    // それ以外  → user.login
);
```

---

## Blade での session 認証 (ユーザー画面)

### フロー (シンプル)

1. ブラウザが `<form action="/login" method="POST">` で送信
2. CSRF token は Blade の `@csrf` ディレクティブで hidden field として埋め込み
3. Laravel は session cookie をセット
4. 以降のリクエストでは cookie が自動送信され、`Auth::user()` で認証 user を取得

### Vite plugin との関係

- **`laravel-vite-plugin`** が `@vite([...])` ディレクティブを provide
- Blade で `@vite(['resources/js/user.js'])` と書くと、開発時は Vite Dev Server へのリンク、
  本番ビルド時は `public/build/manifest.json` を見て hashed asset のパスを出力
- 認証 UI には htmx + Alpine.js を Vite 経由で bundle (CDN 不使用)

```blade
{{-- resources/views/layouts/user.blade.php --}}
<meta name="csrf-token" content="{{ csrf_token() }}">
@vite(['resources/js/user.js'])
```

```js
// resources/js/user.js (htmx + Alpine をローカルバンドル)
import 'htmx.org';
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();
```

詳細: [`htmx-alpine.md`](./htmx-alpine.md)

---

## React SPA での session 認証 (管理画面)

### 同一オリジン session

- Laravel が `<div id="admin-app">` 入りの Blade を返す → React がマウント
- React 内の form は `<form method="POST" action="/admin/login">` で **通常の form submit**
- CSRF token は `<meta name="csrf-token">` から取り出して hidden field に挿入
- Laravel が session cookie をセット、以降のリクエストで自動送信
- 認証中の admin 情報は Blade 側で `window.__adminUser` に注入してから React に渡す

```blade
{{-- resources/views/admin/app.blade.php --}}
<meta name="csrf-token" content="{{ csrf_token() }}">
@vite(['resources/js/admin/app.tsx'])
<div id="admin-app"></div>

@auth('admin')
    @php($admin = auth('admin')->user())
    <script>
      window.__adminUser = {
        id:    {{ $admin->id() }},
        name:  @json($admin->name()),
        email: @json($admin->email())
      };
    </script>
@endauth
```

```tsx
// resources/js/admin/types.ts
export function getCsrfToken(): string {
    const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
    return meta?.content ?? '';
}
```

```tsx
// resources/js/admin/pages/LoginPage.tsx
<form method="POST" action="/admin/login">
  <input type="hidden" name="_token" value={getCsrfToken()} />
  <input type="email" name="email" required />
  <input type="password" name="password" required />
  <button type="submit">ログイン</button>
</form>
```

### fetch ベースで API を叩く場合 (今後の CRUD)

```tsx
const res = await fetch('/admin/api/users', {
    method: 'POST',
    credentials: 'same-origin',                  // session cookie 送信
    headers: {
        'Content-Type': 'application/json',
        'Accept':       'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),          // VerifyCsrfToken が検証
    },
    body: JSON.stringify(payload),
});
```

### Vite plugin との関係

- **`laravel-vite-plugin`**: Blade の `@vite()` が `app.tsx` のビルド成果物 (manifest 経由) をロード
- **`@vitejs/plugin-react`**: TypeScript + JSX のトランスパイル + Fast Refresh
- 開発時: `npm run dev` で HMR (Hot Module Replacement) → ブラウザリロード不要でコード変更が反映
- 本番: `npm run build` で `public/build/assets/app-<hash>.js` 等が生成

```js
// vite.config.js
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/user.js',
                'resources/js/admin/app.tsx',
            ],
            refresh: true,                       // Blade 編集時に Vite から自動 reload
        }),
        react(),
    ],
    server: { host: '0.0.0.0', port: 5173, hmr: { host: 'localhost' } },
});
```

詳細: [`../src/README.md`](../src/README.md) の認証フロー節、[`../README.md`](../README.md) の Vite 節

---

## 他の認証方式との比較

| 方式 | 実装の重さ | 主な用途 | このプロジェクトで採用? | 備考 |
|---|---|---|---|---|
| **Session 認証 (このプロジェクト)** | 軽 | 単一ドメインの Web アプリ (Blade / 同一オリジン SPA) | ✓ | Laravel 標準、cookie + CSRF |
| **Sanctum SPA (cookie)** | 中 | API 分離型 SPA (同一トップレベルドメイン) | (将来モバイル/外部 API 追加時に検討) | Session の上に SPA 用の薄いラッパ。CSRF cookie 取得 + Origin チェック |
| **Sanctum API Token (Bearer)** | 中 | モバイル / CLI / Personal Access Token | (将来) | DB に hashed token 保存、abilities (scope) も持てる |
| **Passport (OAuth2)** | 重 | 外部サービスに OAuth2 を公開、refresh token 必要 | ✗ | full OAuth2 サーバー、ベンダー API 提供向け |
| **JWT (`tymon/jwt-auth` 等)** | 中 | stateless API、複数サーバー間で session 共有したくない | ✗ | token に署名情報が入る、revoke が難しい |
| **Socialite (OAuth クライアント)** | 軽 | Google / GitHub 等でログイン | (将来) | 上記の session / Sanctum と組み合わせ |

### 各方式の選択基準

```
要件 → どの方式を選ぶか
─────────────────────────────────────────────
単一ドメインの Web アプリ (この PR の状態) ……… Session 認証 ✓ 採用済み
同一ドメイン下の Blade + SPA 混在 ………………… Session で十分
別ドメインの SPA を作る (api.example.com など) … Sanctum SPA
モバイルアプリと共通 API ………………………… Sanctum API Token
外部サービスに OAuth2 提供 ……………………… Passport
ステートレスなマイクロサービス ………………… JWT
Google ログイン追加 ………………………………… Socialite (Session の上に重ねる)
```

### Session vs Sanctum SPA の差 (よく聞かれる)

| 観点 | Session 認証 (このプロジェクト) | Sanctum SPA |
|---|---|---|
| 認証 cookie | `laravel_session` | `XSRF-TOKEN` + `laravel_session` |
| CSRF 取得 | `<meta name="csrf-token">` から hidden field | `GET /sanctum/csrf-cookie` で取得 → ヘッダで送る |
| ドメイン | 同一オリジン必須 | サブドメインまで (SESSION_DOMAIN, SANCTUM_STATEFUL_DOMAINS) |
| CORS 設定 | 不要 | 必要 (`config/cors.php`) |
| 学習コスト | 低 | 中 |
| 適用ケース | このプロジェクトのように同一オリジンなら ◎ | フロントを別ドメインに分離する場合に必要 |

このプロジェクトは管理画面も `/admin/*` で同一オリジンなので **Session 認証で十分**。
将来モバイルアプリ等で API 公開するなら、追加で Sanctum API Token を入れる。

---

## ハマりどころ

| 症状 | 原因 | 対処 |
|---|---|---|
| `/admin` 未認証で `/login` (ユーザー側) にリダイレクトされる | `is('admin/*')` だけでは `/admin` 単体にマッチしない | `is('admin', 'admin/*')` で両方カバー (本 PR で修正済み) |
| POST で 419 (Page Expired) | CSRF token 不足 | `<input type="hidden" name="_token" value="{{ csrf_token() }}">` or `@csrf` |
| `Auth::user()` が常に null | guard を指定していない | `Auth::guard('user')->user()` のように明示 |
| `auth:user` middleware で `route('login')` が undefined error | デフォルトの `login` 名のルートがない | `bootstrap/app.php` の `redirectGuestsTo` で guard 別に振り分け (本 PR で対応) |
| Remember Me が効かない | `UserAuth::setRememberToken()` が no-op | 永続化は `UserAuthProvider::updateRememberToken()` 側で Repository 経由 |
| React 側で session cookie が送られない | `fetch` に `credentials: 'same-origin'` が無い | 必ず付ける |
| ログイン後 `intended()` で意図せず `/login` に戻る | session が regenerate されてない | `$request->session()->regenerate()` を attempt 成功後に呼ぶ |
| ガード切替時に session が混ざる | `Auth::guard('user')->logout()` だけだと `admin` の session も残る | `$request->session()->invalidate()` + `regenerateToken()` を logout 時に |

---

## 今後拡張するなら

### 1. パスワードリセット

```bash
docker compose exec app php artisan make:notification PasswordResetNotification
# + Mail driver 設定 (Mailhog / SES / Mailpit)
# + password_reset_tokens テーブル (Laravel 標準 migration あり)
```

### 2. Email Verification

```php
// UserAuth に implements MustVerifyEmail を追加
// user テーブルに email_verified_at カラム追加
// VerificationController 追加
```

### 3. Two-Factor Authentication

- Fortify を入れるか、自前で TOTP / WebAuthn を実装
- 通常は Fortify (Laravel 公式) が楽

### 4. ソーシャルログイン (Socialite)

```bash
composer require laravel/socialite
```

google / github / line / facebook 等のプロバイダ実装。
既存の `UserAuthProvider` の上に socialite_account テーブルを足して紐づける。

### 5. 管理画面で API キーを Issue (Sanctum)

```bash
composer require laravel/sanctum
php artisan install:api
```

`AdminAuth` に `HasApiTokens` trait を追加して、管理画面から `$admin->createToken('name')->plainTextToken` で発行。

---

## 関連

- 規約: [`../CLAUDE.md §4`](../CLAUDE.md) (interface + UserProvider + DI バインド)
- アプリ実装: [`../src/README.md`](../src/README.md) (認証フローの図 + クラス責務表)
- ユーザー画面 UI: [`htmx-alpine.md`](./htmx-alpine.md)
- テスト戦略: [`testing.md`](./testing.md) (認証テストの actingAs 例)
- Laravel 公式:
  - <https://laravel.com/docs/authentication>
  - <https://laravel.com/docs/sanctum>
  - <https://laravel.com/docs/passport>
  - <https://laravel.com/docs/socialite>
