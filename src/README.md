# Laravel アプリ (laravel-base-architecture)

このディレクトリは Laravel 13 のアプリ本体です。

- **インフラ (Docker / Supervisor / cron) は [`../README.md`](../README.md)** を参照
- **コーディング規約は [`../CLAUDE.md`](../CLAUDE.md)** を参照

このドキュメントでは以下を説明します:

- ファイル / ディレクトリ構造の責務
- **認証の仕組み** (User / Admin の 2 ガード)
- リクエスト処理のレイヤー構造

---

## ディレクトリ構造

```
src/
├── app/
│   ├── Auth/                              自前 Authenticatable + UserProvider 実装
│   │   ├── User/
│   │   │   ├── UserAuth.php               Authenticatable 実装 (UserRow を包む)
│   │   │   └── UserAuthProvider.php       UserProvider 実装 (UserRepository 経由)
│   │   └── Admin/
│   │       ├── AdminAuth.php
│   │       └── AdminAuthProvider.php
│   ├── Console/Commands/                  artisan コマンド (薄い、Service に委譲)
│   ├── Http/
│   │   ├── Controllers/                   薄い (Request → Service → Resource/ViewModel)
│   │   │   ├── User/
│   │   │   │   ├── Auth/LoginController.php
│   │   │   │   └── DashboardController.php
│   │   │   └── Admin/
│   │   │       ├── Auth/LoginController.php
│   │   │       └── DashboardController.php
│   │   ├── Requests/                      FormRequest (フィールド public const string)
│   │   │   ├── User/Auth/LoginRequest.php
│   │   │   └── Admin/Auth/LoginRequest.php
│   │   ├── Resource/                      HTTP レスポンス用 DTO (Arrayable)
│   │   └── ViewModel/                     Blade 用 DTO (object)
│   │       └── User/DashboardViewModel.php
│   ├── Jobs/                              非同期 Job (job コンテナの queue:work が処理)
│   ├── Models/                            Eloquent モデル (規約上は使わない、Laravel 標準位置のみ残置)
│   └── Providers/
│       └── AppServiceProvider.php         Auth::provider() + interface→Impl DI バインド
├── Demo/                                  事業ドメインパッケージ (CLAUDE.md §4)
│   ├── Service/
│   │   ├── User/
│   │   │   ├── UserAuthService.php        interface
│   │   │   └── UserAuthServiceImpl.php    実装 (Repository を組み合わせる)
│   │   └── Admin/
│   │       ├── AdminAuthService.php
│   │       └── AdminAuthServiceImpl.php
│   └── Repository/
│       ├── User/
│       │   ├── UserRepository.php         interface
│       │   ├── UserRepositoryImpl.php     DB::table('user') クエリビルダー実装
│       │   └── UserRow.php                readonly DTO
│       └── Admin/
│           ├── AdminRepository.php
│           ├── AdminRepositoryImpl.php
│           └── AdminRow.php
├── bootstrap/app.php                      redirectGuestsTo / redirectUsersTo を guard 別に分岐
├── config/auth.php                        guards (user/admin) + providers (userauth/adminauth)
├── database/
│   ├── migrations/
│   │   ├── 2026_05_24_154404_create_user_table.php
│   │   └── 2026_05_24_154408_create_admin_table.php
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── UserSeeder.php                 user@example.com / password
│       └── AdminSeeder.php                admin@example.com / password
├── resources/
│   ├── views/                             Blade (ユーザー画面)
│   │   ├── layouts/user.blade.php         htmx + Alpine.js を CDN 読み込み
│   │   ├── user/auth/login.blade.php
│   │   ├── user/dashboard.blade.php       $vm (ViewModel) でデータ参照
│   │   └── admin/app.blade.php            React マウントポイント
│   └── js/admin/                          React (管理画面)
│       ├── app.tsx                        エントリ + React Router
│       ├── app.css
│       ├── types.ts                       window グローバル型 + getCsrfToken
│       ├── pages/
│       │   ├── LoginPage.tsx              Bootstrap form + FontAwesome
│       │   └── DashboardPage.tsx          navbar + card
│       └── components/                    (今は空、共通 UI を置く場所)
├── routes/
│   ├── web.php                            user/admin の認証ルート + ダッシュボード保護
│   └── console.php                        Schedule (cron 経由)
└── composer.json                          psr-4 で App\ + Demo\ を登録
```

---

## 認証の仕組み

### 全体フロー

```
[Browser]
    │ POST /login (email + password)
    ▼
[User\Auth\LoginController@login]
    │ LoginRequest で検証
    ▼
Auth::guard('user')->attempt([...])
    │
    ▼
[App\Auth\User\UserAuthProvider]  (Laravel UserProvider 実装)
    ├─ retrieveByCredentials  → UserRepository::findByEmail
    └─ validateCredentials    → password_verify
    │ 認証 OK
    ▼
[App\Auth\User\UserAuth] を session に格納
    │
    ▼
redirect /dashboard (auth:user middleware で保護)
    │
    ▼
[User\DashboardController@index]
    │ Auth::guard('user')->user() で UserAuth 取得
    ▼
DashboardViewModel::fromAuth($auth) で詰め替え
    │
    ▼
view('user.dashboard', ['vm' => $vm])
    │
    ▼
[Browser] $vm->userName 等を表示
```

Admin も同じ流れで、ガード名と Provider が `admin` / `adminauth` に変わるだけ。

### 主要クラスの責務

| 層 | クラス | 役割 |
|---|---|---|
| Laravel | `Auth::guard('user')` / `Auth::guard('admin')` | Auth ファサード、session に認証中の Authenticatable を保持 |
| Auth 層 | `App\Auth\User\UserAuthProvider` | `UserProvider` interface 実装、Repository 経由で DB アクセス |
| Auth 層 | `App\Auth\User\UserAuth` | `Authenticatable` interface 実装、`UserRow` を包む readonly オブジェクト |
| Service 層 | `Demo\Service\User\UserAuthService` | 新規登録 (`Hash::make` してから Repository に渡す) |
| Repository 層 | `Demo\Repository\User\UserRepository` | interface (`findById`, `findByEmail`, `insert`, `updateRememberToken`) |
| Repository 層 | `Demo\Repository\User\UserRepositoryImpl` | **`DB::table('user')` クエリビルダー実装** |
| DTO | `Demo\Repository\User\UserRow` | readonly DTO (Eloquent モデルを Service に漏らさないための変換層) |

Admin も同じ構造 — `App\Auth\Admin\` / `Demo\Service\Admin\` / `Demo\Repository\Admin\`。

### ガード設定 (`config/auth.php`)

```php
'guards' => [
    'web'   => [...],                                  // Laravel 標準 (未使用)
    'user'  => ['driver' => 'session', 'provider' => 'user'],
    'admin' => ['driver' => 'session', 'provider' => 'admin'],
],

'providers' => [
    'users' => [...],                                  // Laravel 標準 (未使用)
    'user'  => ['driver' => 'userauth'],               // ↓ で登録するカスタム driver
    'admin' => ['driver' => 'adminauth'],
],
```

### カスタム driver の登録 (`app/Providers/AppServiceProvider::boot()`)

```php
Auth::provider('userauth',  fn ($app) => $app->make(UserAuthProvider::class));
Auth::provider('adminauth', fn ($app) => $app->make(AdminAuthProvider::class));
```

### ルートミドルウェア (`routes/web.php`)

```php
// ユーザー
Route::middleware('guest:user')->group(fn () => /* /login など */);
Route::middleware('auth:user')->group(fn () => /* /dashboard など */);

// 管理者
Route::middleware('guest:admin')->group(fn () => /* /admin/login */);
Route::middleware('auth:admin')->group(fn () => /* /admin/* (React SPA) */);
```

### 未認証時のリダイレクト先 (`bootstrap/app.php`)

Laravel 標準の `auth` middleware は未認証時に `route('login')` を呼ぶデフォルトだが、
このプロジェクトには `login` という名前のルートはない (`user.login` / `admin.login` に分かれている)。
そのため、URL パターンに応じて振り分ける必要がある:

```php
$middleware->redirectGuestsTo(
    fn ($request) => $request->is('admin/*')
        ? route('admin.login')
        : route('user.login')
);
```

`redirectUsersTo` (認証済みユーザーが guest 画面に来たときのリダイレクト先) も同様に分岐。

### Remember Me / トークン

`UserAuth::setRememberToken()` は **no-op**。永続化は `UserAuthProvider::updateRememberToken()` が
Repository 経由で行う (UserAuth を immutable に保つため)。

### 管理画面 (React) の CSRF / 認証情報

React は SPA だが session 認証 (同一オリジン)。`<meta name="csrf-token">` を Blade から渡し、
React 内の form で `_token` hidden field として送信:

```tsx
function getCsrfToken(): string {
    const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
    return meta?.content ?? '';
}
```

ログイン後の管理者情報は Blade から `window.__adminUser` に注入し、React 側で参照:

```blade
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

---

## レイヤー構造 (詳細は [`../CLAUDE.md §4`](../CLAUDE.md))

```
[Browser]
   ↓ HTTP リクエスト
[Controller (app/Http/Controllers/)]                    薄く
   ├─ FormRequest (app/Http/Requests/) で自動検証
   ↓ Service にプリミティブ / DTO で渡す
[Service (Demo/Service/<Logic>/)]                       ビジネスロジック
   ├─ トランザクション境界はここ
   ↓ Repository 経由で外部と通信
[Repository (Demo/Repository/<Logic>/)]                 永続化抽象
   ├─ DB: DB::table('user')->... (クエリビルダー基本)
   └─ 外部 API: Http::post(...)
   ↓ 結果は Row (readonly DTO) で返す
[Controller] が結果を整形して返す
   ├─ JSON: Resource (app/Http/Resource/<Role>/<Entity>/)
   └─ Blade: ViewModel (app/Http/ViewModel/<Domain>/<SubDomain>/)
   ↓
[Browser]
```

### 各層の鉄則

- **Controller** は Service を呼び、結果を Resource / ViewModel で整形するだけ。`DB::` `Http::` `Storage::` を直接呼ばない (`../CLAUDE.md §4`)
- **Service** は Repository 経由でしか外部と通信しない
- **Repository は interface 必須**、`DB::table()` クエリビルダーが基本 (Eloquent モデルを Service に漏らさない)
- **interface には PHPDoc 必須**、`~Impl` の public は書かない (private は書く)。詳細は `../CLAUDE.md §4` の interface PHPDoc 規約

---

## composer.json autoload

```json
"autoload": {
    "psr-4": {
        "App\\": "app/",
        "Database\\Factories\\": "database/factories/",
        "Database\\Seeders\\": "database/seeders/",
        "Demo\\": "Demo/"
    }
}
```

- `App\` (Laravel 標準) と `Demo\` (事業ドメイン) を並列で登録
- 新規ファイルを置いたら必ず:

```bash
docker compose exec app composer dump-autoload
```

---

## Laravel 13 ひな型から残置している (規約に合わないが触らない)

| 項目 | 理由 |
|---|---|
| `app/Models/User.php` (Eloquent モデル) | 認証は `App\Auth\User\UserAuth` を使うため未使用。ただし削除すると Laravel の暗黙参照 (`config/auth.php` の `users` provider 等) が壊れるので残置 |
| `database/migrations/0001_..._create_users_table.php` (`users` 複数形) | 認証は `user` 単数形テーブルを使うため未使用。`sessions` / `password_reset_tokens` を含むので削除しない |
| `database/migrations/0001_..._create_cache_table.php` (`cache` 複数形) | Cache facade が暗黙参照。残置 |
| `database/migrations/0001_..._create_jobs_table.php` (`jobs` 複数形) | Queue (`QUEUE_CONNECTION=database`) が使用。残置 |

新規に作るテーブルは [`../CLAUDE.md §2`](../CLAUDE.md) に従って **単数形** で統一。

---

## 動作確認

```bash
# テストアカウントでログイン
open http://localhost:8080/login         # user@example.com / password
open http://localhost:8080/admin/login   # admin@example.com / password
```

詳しい動作確認手順は [`../README.md`](../README.md#動作確認-テストアカウント) を参照。
