# Laravel Docker 環境

Laravel 開発用の Docker Compose 環境。
**nginx + PHP-FPM 8.4 + PostgreSQL 16 + Node.js 20 (Vite) + Supervisor (Queue / Scheduler)** 構成。
タイムゾーンとロケールは **Asia/Tokyo / ja_JP.UTF-8** に統一。

> **コーディング規約 (命名 / レイヤー / Service / Repository / interface PHPDoc / Job など)**
> **は [`CLAUDE.md`](CLAUDE.md) を参照。** README はインフラ・セットアップ・運用にフォーカス。

---

## 構成

```
   ┌────────────────────── app 系 (HTTP) ──────────────────────┐
   │                                                            │
   │  Browser ─ :8080 ─> [web (nginx)] ─ fastcgi ─> [app (PHP-FPM)]
   │  Browser ─ :5173 ─> [app (Vite Dev サーバー / HMR)]        │
   │                                  │                         │
   └──────────────────────────────────┼─────────────────────────┘
                                      │
                              ┌───────┴───────┐
                              ▼               ▼
                         [job]           [batch]
                         supervisord     supervisord
                          └─ queue:work    ├─ cron -f
                             --queue=default └─ tail -F
                             (memory=256)        scheduler-cron.log
                          (Bus::batch も        (cron が schedule:run
                           ここで処理)           を毎分発火)
                              │               │
                              └───────┬───────┘
                                      ▼
                              [db (PostgreSQL 16)]
                              jobs / job_batches / failed_jobs
```

役割は **3 種類 (app / job / batch)**、コンテナは 5 つ:

| 役割 | サービス | 内容 | ホスト公開ポート |
|---|---|---|---|
| **app 系 (HTTP)** | `web` | nginx 1.27 (Laravel 用 vhost) — app のサイドカー | `8080` → `80` |
| | `app` | PHP 8.4 FPM + Composer + Node.js 20 | `5173` (Vite) |
| | `db`  | PostgreSQL 16 — app のサイドカー | `5432` |
| **job 系** | `job` | supervisord + `queue:work --queue=default`。**通常 Job も Bus::batch の Job もここで処理** | — |
| **batch 系** | `batch` | supervisord + cron daemon + tail。**時刻トリガで schedule:run を毎分実行** (Job 本体は実行しない) | — |

worker 系コンテナ (`job` / `batch`) は同じ `laravelarche-app:latest` イメージを使い回し、
bind mount する `supervisord-*.conf` だけが違う。

> ⚠️ **「`batch` コンテナ」と Laravel の「`Bus::batch` 機能」は別物**。
> `batch` コンテナは cron daemon (時刻トリガ)、`Bus::batch` の Job は `job` コンテナで処理されます。
> 詳細は [`CLAUDE.md §7`](CLAUDE.md)。

---

## 必要なもの

- Docker Desktop (Mac / Windows) もしくは Docker Engine + Compose v2 (Linux)
- それだけ (ホストに PHP / Node / Composer のインストールは不要)

---

## ディレクトリ構造

```
.
├── docker-compose.yml
├── .env.example                  ホスト側 UID / GID
├── .gitignore
├── README.md                     ← この文書 (インフラ / セットアップ / 運用)
├── CLAUDE.md                     ← コーディング規約 (Laravel / PHP)
├── .claude/
│   └── agents/
│       └── laravel-code-reviewer.md   規約チェック用サブエージェント
├── docker/
│   ├── nginx/
│   │   ├── Dockerfile
│   │   └── default.conf          Laravel 用 vhost
│   ├── php/
│   │   ├── Dockerfile                 PHP 8.4 + 拡張 + Composer + Node.js + Supervisor + cron
│   │   ├── php.ini                    date.timezone / mbstring 等
│   │   ├── www.conf                   php-fpm プール
│   │   ├── supervisord-job.conf       job コンテナ用 (queue:work --queue=default)
│   │   └── supervisord-batch.conf     batch コンテナ用 (cron daemon + tail)
│   ├── cron/
│   │   └── laravel-scheduler          batch コンテナの /etc/cron.d/ に bind mount される crontab
│   └── postgres/
│       └── Dockerfile            ja_JP.UTF-8 + Asia/Tokyo
└── src/                          ← Laravel 本体
    ├── app/
    │   ├── Http/{Controllers, Requests, Resources}/
    │   ├── Jobs/                 Job クラス (Laravel 標準位置)
    │   ├── Console/Commands/     artisan コマンド
    │   ├── Enums/
    │   └── Constants/
    └── Demo/                     ← 事業ドメインパッケージ (app/ と並列)
        ├── Service/<Logic>/      Service interface + Impl
        └── Repository/<Logic>/   Repository interface + Impl (DB クエリビルダー / 外部 API)
```

`src/Demo/` 配下の構成と命名規則は `CLAUDE.md §4` を参照。

---

## セットアップ (新規 clone から)

### 1. ホスト UID/GID を埋め込む

```bash
cp .env.example .env
printf "UID=%s\nGID=%s\n" "$(id -u)" "$(id -g)" > .env
```

### 2. ビルド & 起動

```bash
docker compose build
docker compose up -d
```

### 3. Laravel を `src/` にインストール (初回のみ)

```bash
docker compose exec app composer create-project laravel/laravel .
```

> `src/` に隠しファイル (`.DS_Store` 等) があると Composer に弾かれる。
> 事前に `find src -name '.DS_Store' -delete` しておく。

### 4. Laravel の `.env` を DB に合わせる (`src/.env` を編集)

```env
APP_TIMEZONE=Asia/Tokyo
APP_LOCALE=ja
APP_FAKER_LOCALE=ja_JP

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret

QUEUE_CONNECTION=database
```

### 5. Laravel 13 ひな型の timezone を `env()` に修正

`src/config/app.php` の `'timezone' => 'UTC'` を次に置き換える:

```php
'timezone' => env('APP_TIMEZONE', 'UTC'),
```

> Laravel 13 のひな型はハードコードのままで `APP_TIMEZONE` が効かない。詳細は `CLAUDE.md §6`。

### 6. Demo パッケージのオートロード登録

`src/composer.json` の `autoload.psr-4` に追記:

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

反映:

```bash
docker compose exec app composer dump-autoload
```

### 7. キー生成 + マイグレーション

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

→ <http://localhost:8080> を開いて起動確認。

---

## Vite (フロントエンド開発)

Laravel ひな型の `laravel-vite-plugin` をそのまま使う。
Docker からホストに公開するため `src/vite.config.js` の `defineConfig` に
`server` ブロックを追記する (初回セットアップで設置済み)。

```js
// src/vite.config.js
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        host: '0.0.0.0',                              // ホストから到達可能に
        port: 5173,
        hmr: { host: 'localhost' },                   // ブラウザ → Vite の HMR
        watch: { usePolling: true, interval: 500 },   // bind mount 用
    },
});
```

起動:

```bash
docker compose exec app npm install
docker compose exec -d app npm run dev    # バックグラウンドで Dev サーバー
```

Blade では:

```blade
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

本番ビルド:

```bash
docker compose exec app npm run build
```

---

## Job / Batch (役割 2 種類 / コンテナ 2 つ)

| コンテナ | supervisord.conf | 中身 |
|---|---|---|
| `job` | `supervisord-job.conf` | `queue:work --queue=default --tries=3 --backoff=10 --max-time=3600 --memory=256 --sleep=3` |
| `batch` | `supervisord-batch.conf` + `docker/cron/laravel-scheduler` | **cron daemon** (`/usr/sbin/cron -f -L 4`) + `tail -F scheduler-cron.log` |

`job` コンテナは **通常 dispatch の Job も Bus::batch の Job も同じ default queue で処理** します
(queue 分離は最初は作らない、必要になったら supervisord に program を追加すればよい)。

`batch` コンテナは **時刻トリガ専用** で、cron daemon が毎分 `php artisan schedule:run` を呼ぶだけ。
`Schedule::job(...)` で登録された Job は job コンテナへ dispatch され、
`Schedule::command(...)` / `Schedule::call(...)` は batch コンテナ内で直接実行されます。

> ⚠️ **「`batch` コンテナ」と Laravel の `Bus::batch()` 機能は別物**。
> 「batch だから `->onQueue('batch')` が必要」のような書き方は **不要**。
> 全 Job は default queue に流して job コンテナで処理されます。

```php
// 通常 dispatch / Bus::batch、どちらも書き方は同じ (queue 指定不要)
ImportUserCsvJob::dispatch('a.csv');

Bus::batch([
    new ImportUserCsvJob('chunk-1.csv'),
    new ImportUserCsvJob('chunk-2.csv'),
])->name('user import')->dispatch();
```

詳細は [`CLAUDE.md §7`](CLAUDE.md)。

### なぜ scheduler を Laravel 公式 cron daemon にしているか

`schedule:work` (Laravel 11+ 限定) もシェルループも採らない。理由:

- cron は Linux 標準で、運用ツール / モニタリングが cron 前提で揃っている
- Laravel 10 / 11 / 12 / 13 すべてで同じ書き方
- `MAILTO=""` / `PATH` 等の cron 標準セマンティクスがそのまま使える

cron job の出力は `storage/logs/scheduler-cron.log` に書き出され、supervisor の `cron-tail` program が
`tail -F` で stdout に流すので `docker compose logs batch` から普通に見える
(`/proc/1/fd/1` への直接 redirect は supervisord 所有で www-data から書けないためこの構成)。

### Supervisor 操作

```bash
# 各コンテナの supervisor 配下プロセス状況
docker compose exec job   supervisorctl status
docker compose exec batch supervisorctl status

# 個別再起動 (config 変更などを反映したいとき)
docker compose exec job   supervisorctl restart job-worker
docker compose exec batch supervisorctl restart cron

# 失敗 Job 確認 / 再実行 / 全クリア
docker compose exec app php artisan queue:failed
docker compose exec app php artisan queue:retry all
docker compose exec app php artisan queue:flush
```

---

## よく使うコマンド

| 操作 | コマンド |
|---|---|
| 起動 | `docker compose up -d` |
| 停止 | `docker compose stop` |
| 完全破棄 (DB ボリュームも削除) | `docker compose down -v` |
| ログ追跡 | `docker compose logs -f app` (web / job / batch / db も同様) |
| app コンテナのシェルに入る | `docker compose exec app bash` |
| artisan | `docker compose exec app php artisan <cmd>` |
| composer | `docker compose exec app composer <cmd>` |
| npm | `docker compose exec app npm <cmd>` |
| psql | `docker compose exec db psql -U laravel laravel` |
| tinker | `docker compose exec -e HOME=/tmp app php artisan tinker` |
| supervisor 状態 | `docker compose exec {job,batch} supervisorctl status` |

> `tinker` で `HOME=/tmp` を渡しているのは、psysh のヒストリ書込先が www-data ホームディレクトリ
> (`/var/www/.config`) に作れずに warning が出るため。

---

## Tinker (デバッグ)

Laravel の REPL (PsySH ベース)。DB クエリ確認、Job dispatch、config / env の値確認、
Service / Repository の動作チェックに使う。

### 起動

```bash
# 対話モードで起動 (exit / Ctrl+D で抜ける)
docker compose exec -e HOME=/tmp app php artisan tinker

# 1 コマンド実行 (one-shot)
docker compose exec -e HOME=/tmp app php artisan tinker --execute='DB::table("user")->count();'
```

### よくある用途

```php
// ─── DB クエリビルダー ───
DB::table('user')->where('status', 'active')->get();
DB::table('user')->where('id', 1)->first();
DB::table('user')->count();

// ─── Carbon (タイムゾーン確認) ───
now();                                  // Carbon\Carbon @ Asia/Tokyo
now()->format('Y-m-d H:i:s T');         // "2026-05-24 14:01:42 JST"

// ─── config / env の確認 ───
config('app.timezone');                 // "Asia/Tokyo"
config('queue.default');                // "database"
env('APP_TIMEZONE');                    // "Asia/Tokyo"

// ─── Job を投入 (実 Job クラスで) ───
\App\Jobs\SendWelcomeMailJob::dispatch(123);

// ─── DI コンテナから Service / Repository を取り出す ───
app(\Demo\Service\User\UserService::class)->getActiveUserList();
app(\Demo\Repository\User\UserRepository::class)->findById(1);

// ─── Route 一覧 ───
collect(\Route::getRoutes())->map(fn($r) => $r->methods()[0] . ' ' . $r->uri());

// ─── 直近のクエリログ ───
DB::enableQueryLog();
DB::table('user')->where('id', 1)->first();
DB::getQueryLog();                      // 実行された SQL とバインド値
```

### 注意点

- **クロージャ dispatch は使えない** — `dispatch(function() { ... })` は tinker (eval) では
  `SerializableClosure` がソースファイルを読めずエラーになる。
  必ず実 Job クラス (`src/app/Jobs/...`) を作って `MyJob::dispatch()` で投入する
- **`HOME=/tmp` を必ず付ける** — 付けないと `Writing to directory /var/www/.config/psysh is not allowed.`
  という warning が出る (動作には影響しないが見栄えが悪い)
- **本番で直接データ更新はしない** — 開発・調査用に留める。本番のデータ修正は必ず
  artisan コマンド (`app/Console/Commands/`) を作って履歴に残す形で実行する

### ブラウザリクエスト経由でのデバッグ (dd / dump / logger)

API / 画面リクエストの中身を見たい場合は tinker より `dd()` / `dump()` が早い。

```php
// Controller / Service / Blade のどこでも
dd($user);                              // dump して die (レスポンスを止める)
dump($user);                            // dump して継続 (HTML に出る)
logger($user);                          // storage/logs/laravel.log に記録
logger()->info('hit', ['id' => $userId]);
```

ログを追跡:

```bash
docker compose exec app tail -f storage/logs/laravel.log
```

worker (job コンテナ内) のジョブで使うときも `logger()` でログに書けば、上のコマンドで一緒に追える。

### Queue / Job の挙動を確認するワンライナー

```bash
# 投入直後の jobs テーブル状態
docker compose exec -T db psql -U laravel -d laravel \
  -c "SELECT id, queue, attempts, available_at FROM jobs;"

# 失敗したジョブの中身
docker compose exec -T db psql -U laravel -d laravel \
  -c "SELECT id, queue, exception FROM failed_jobs ORDER BY id DESC LIMIT 3;"

# supervisor 配下プロセスの稼働確認 (job + batch コンテナ)
docker compose exec job   supervisorctl status
docker compose exec batch supervisorctl status
```

---

## コーディング規約

このリポジトリのコーディング規約は [`CLAUDE.md`](CLAUDE.md) に集約しています。

| § | 内容 |
|---|---|
| 1 | 定数 / Enum (大文字スネークケース) |
| 2 | DB テーブル名 (単数形 `user` / `tag`) |
| 3 | 変数・関数命名 (`~List` サフィックス、略語禁止、命名から処理が予測可能) |
| 4 | レイヤー構造 (Controller → Service → Repository) + interface PHPDoc 規約 |
| 5 | 制御フロー (早期 return / match) |
| 6 | その他 (`declare(strict_types=1)` / `final` / `Carbon::now()`) |
| 7 | Job / Batch / Schedule (Supervisor 経由運用) |

`.claude/agents/laravel-code-reviewer.md` に、規約違反を検出する確認専用サブエージェントを置いています。
コードを書いた後に「規約レビュー」と頼めば走ります。
