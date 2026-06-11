# Tinker 活用ガイド + artisan 確認系コマンド

`php artisan tinker` は Laravel が標準で同梱する REPL (PsySH ベース)。
コントローラを書かなくても、フレームワーク全体 (DI / Eloquent / Cache / Queue / Auth / 設定)
にアクセスして手元で叩ける。

このプロジェクトは Eloquent を使わず Repository + Service の自前 DI 設計なので、
**tinker で `app(...)->...` 経由で Service / Repository を直接叩く** のが基本パターン。
本ガイドは、その呼び出し方と artisan 確認系コマンドを集約する。

---

## 1. 起動方法

```bash
# 通常起動 (対話シェル)
docker compose exec app php artisan tinker

# 1 行だけ実行 (CI / スクリプトから)
docker compose exec app php artisan tinker --execute="echo App::version();"
```

`exit` / `quit` / Ctrl+D で終了。
複数行貼り付けたいときは `>>>` プロンプトに直接ペーストすればよい
(PsySH が改行を解釈する)。

```
Psy Shell v0.12.x (PHP 8.4.x — cli) by Justin Hileman
> echo 'hello';
hello
> App::version()
= "13.x.x"
> exit
```

---

## 2. このプロジェクトでの基本パターン (DI 経由で Service / Repository を叩く)

### 2.1 Service を呼ぶ

```php
// Service 経由でユーザー登録
$service = app(\Demo\User\Service\UserAuthService::class);
$userId  = $service->register(name: 'tinker太郎', email: 'tinker@example.com', plainPassword: 'password123');

// 登録した user を Repository から取得 (App\Model\User\User が返る)
$user = app(\Demo\User\Repository\UserRepository::class)->findById($userId);
$user->getName();   // "tinker太郎"
$user->getEmail();  // "tinker@example.com"
```

### 2.2 Repository を直接叩く (DB の中身を確認)

```php
// 全件
$repo = app(\Demo\User\Repository\UserRepository::class);
$repo->findById(1);

// クエリビルダーで直接覗く
use Illuminate\Support\Facades\DB;
DB::table('user')->get();
DB::table('user')->count();
DB::table('admin')->where('email', 'admin@example.com')->first();
```

### 2.3 認証ガードの状態を見る

```php
// guard:user / guard:admin の現在の認証ユーザー
auth('user')->user();
auth('admin')->user();

// ID 指定でログインさせる (CLI セッションなので Cookie は飛ばないが Auth::id() は通る)
auth('user')->loginUsingId(1);
auth('user')->id();        // 1
auth('user')->logout();
```

### 2.4 Hash / Crypt / URL 生成

```php
use Illuminate\Support\Facades\Hash;
Hash::make('password123');
Hash::check('password123', '$2y$12$....');   // bool

route('user.login');                          // 名前付きルート
url('/dashboard');                            // 絶対 URL
config('app.url');                            // 設定値
```

### 2.5 Job を dispatch / Bus::batch

```php
use App\Jobs\SendWelcomeMailJob;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

// 通常 dispatch (job コンテナの queue:work が拾う)
SendWelcomeMailJob::dispatch(1);

// Bus::batch (こちらも job コンテナで処理される。queue 分離はしていない)
Bus::batch([
    new SendWelcomeMailJob(1),
    new SendWelcomeMailJob(2),
])->name('welcome mails')->dispatch();

// 失敗ジョブ確認
DB::table('failed_jobs')->latest('failed_at')->limit(5)->get();
DB::table('job_batches')->latest('created_at')->limit(5)->get();
```

> ⚠️ **tinker の dispatch(function () { ... }) は失敗する**。
> `SerializableClosure` が REPL の eval ソースを取り出せないため。
> Job として登録したクラス (`App\Jobs\~Job`) を使う。

### 2.6 Cache / Session / Config を覗く

```php
use Illuminate\Support\Facades\Cache;

Cache::put('foo', 'bar', 60);
Cache::get('foo');         // "bar"
Cache::pull('foo');        // 取り出して削除

config('database.default');                  // "pgsql"
config('logging.default');                   // "stderr"
config('queue.connections.database');        // 配列
```

---

## 3. よく使うスニペット集

### 3.1 user/admin テーブルの中身

```php
DB::table('user')->select('id', 'name', 'email', 'created_at')->get();
DB::table('admin')->select('id', 'name', 'email', 'created_at')->get();
```

### 3.2 全テーブル一覧

```php
DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename;");
```

### 3.3 直近の失敗 Job のスタックトレース

```php
DB::table('failed_jobs')
    ->latest('failed_at')
    ->first()
    ->exception;   // string (Throwable::__toString 出力)
```

### 3.4 セッション件数 (database driver)

```php
DB::table('sessions')->count();
DB::table('sessions')->orderByDesc('last_activity')->limit(5)->get();
```

### 3.5 Carbon でタイムゾーン確認

```php
use Carbon\Carbon;
Carbon::now();                       // Asia/Tokyo (APP_TIMEZONE)
Carbon::now('UTC');
date_default_timezone_get();         // "Asia/Tokyo"
```

---

## 4. artisan 確認系コマンド一覧

「ちょっと挙動を見たい / 設定を確認したい」ときに tinker を起動するより速いものたち。
**Read-only / 副作用なし** のものだけを並べる (cache:clear や migrate のような変更系は除外)。

### 4.1 アプリ全体の状態

| コマンド | 用途 |
|---|---|
| `php artisan about` | バージョン / 環境 / cache 状態 / drivers を一覧 (最初に打つやつ) |
| `php artisan env` | 現在の APP_ENV (local / production など) |

### 4.2 ルート

| コマンド | 用途 |
|---|---|
| `php artisan route:list` | 全ルート (method / URI / name / action / middleware) |
| `php artisan route:list --except-vendor` | vendor 由来のルートを隠して見やすく |
| `php artisan route:list --path=admin` | パスでフィルタ |
| `php artisan route:list --name=login` | 名前でフィルタ |

### 4.3 設定 / 環境

| コマンド | 用途 |
|---|---|
| `php artisan config:show app` | `config/app.php` の最終値 (env 反映後) を JSON 風に表示 |
| `php artisan config:show database.connections.pgsql` | 入れ子のキーも参照可 |
| `php artisan config:show logging` | logging の全 channel と driver を一覧 |

### 4.4 DB / マイグレーション

| コマンド | 用途 |
|---|---|
| `php artisan db` | psql に入る (このプロジェクトは pgsql) |
| `php artisan db:show` | DB 接続情報 + テーブル一覧 + サイズ |
| `php artisan db:table user` | `user` テーブルのカラム / インデックスを表示 |
| `php artisan db:monitor` | 接続数の現状 (運用時) |
| `php artisan migrate:status` | 各 migration の Ran / Pending 状態 |
| `php artisan schema:dump` | 現状のスキーマを `database/schema/` に dump (任意) |

### 4.5 Queue / Job

| コマンド | 用途 |
|---|---|
| `php artisan queue:failed` | 失敗 Job 一覧 (再実行は `queue:retry all`) |
| `php artisan queue:monitor default` | 指定 queue の積み残しサイズ |

### 4.6 Schedule

| コマンド | 用途 |
|---|---|
| `php artisan schedule:list` | 登録されている全 Schedule (次回実行時刻つき) |
| `php artisan schedule:test` | 対話的に Schedule タスクを 1 つ選んで即時実行 |

### 4.7 Event / Channel

| コマンド | 用途 |
|---|---|
| `php artisan event:list` | 登録 Event と Listener の対応表 |
| `php artisan channel:list` | Broadcasting チャネル一覧 |

### 4.8 リアルタイムログ tail (Pail)

| コマンド | 用途 |
|---|---|
| `php artisan pail` | Laravel ログをカラーで tail (level / filter 付き) |
| `php artisan pail --level=error` | error 以上だけ |
| `php artisan pail --filter="user_id=1"` | 文字列フィルタ |

このプロジェクトは `LOG_CHANNEL=stderr` で stdout/stderr 出力なので、
`docker compose logs -f app` でも同じものが見える。pail は Laravel 整形済み JSON / 色つきが欲しいとき用。

---

## 5. tinker の便利機能 (PsySH)

| 操作 | 効果 |
|---|---|
| `doc DB::table` | メソッドのドキュメントを表示 |
| `ls App\\Services\\` | 名前空間配下の class / interface 一覧 |
| `show App\\Auth\\User\\UserAuth` | クラスのソースを直接表示 |
| `whereami` | 現在のコンテキスト (debug 中に有用) |
| `history` | 過去のコマンド履歴 |
| `clear` | 画面クリア |

履歴は `~/.config/psysh/psysh_history` に保存される
(コンテナ内 `www-data` のホーム配下)。

---

## 6. トラブルシュート

### 6.1 `Class "X" not found`

- composer のオートロードが古い: `docker compose exec app composer dump-autoload`
- `Demo/` 配下を増やしたあとは composer 再生成必須

### 6.2 `SerializableClosure cannot serialize ...` (Job dispatch 時)

- tinker で `dispatch(function () { ... })` は不可。
  → Job クラス (`App\Jobs\~Job`) を作って `~Job::dispatch(...)` に書き換える。

### 6.3 DB に書いたけど見えない

- トランザクション内 (`DB::beginTransaction()`) のまま `quit` していないか
- 別セッションから見て本当にコミット済みか:
  `docker compose exec db psql -U laravel -d laravel -c 'SELECT count(*) FROM "user";'`

### 6.4 ログが出ない

- このプロジェクトは `LOG_CHANNEL=stderr` なので `storage/logs/*.log` には書かれない。
  `docker compose logs -f app` か `php artisan pail` で見る。

---

## 7. 関連ドキュメント

- [docs/testing.md](testing.md) — テストの書き方 (tinker で挙動を見たあと、テストに落とすのが理想)
- [docs/queue.md](queue.md) — Queue / Job の使い方 (tinker 内 dispatch の補足)
- [docs/schedule.md](schedule.md) — Schedule の使い方 (`schedule:list` で確認)
- [docs/authentication.md](authentication.md) — 認証 (tinker から `auth('user')` を叩くときの参考)
