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

## 動作確認 (テストアカウント)

`UserSeeder` / `AdminSeeder` が以下のテストアカウントを投入します
(`docker compose exec app php artisan db:seed --force`)。

| 画面 | URL | テストアカウント | 技術スタック |
|---|---|---|---|
| **ユーザー画面** | <http://localhost:8080/login> | `user@example.com` / `password` | Blade + htmx + Alpine.js (CDN) |
| **管理画面** | <http://localhost:8080/admin/login> | `admin@example.com` / `password` | Vite + React 18 + TypeScript + Bootstrap 5 + FontAwesome |

### ユーザー画面で試せること

- `user@example.com` / `password` でログイン → `/dashboard` へリダイレクト
- ダッシュボードで:
  - **htmx デモ** — 「サーバー時刻取得」ボタン (`hx-get` で `/api/server-time` を fetch、`hx-target` で innerHTML 差し替え)
  - **Alpine.js デモ** — カウンタ (`x-data` + `x-text` + `@click`)
  - パスワード入力欄の「表示/隠す」トグル (`x-model`)

### 管理画面で試せること

- `admin@example.com` / `password` でログイン → `/admin` へリダイレクト
- React Router で `/admin/login` → `/admin` を SPA 内ナビゲーション
- Bootstrap の navbar / card / button
- FontAwesome アイコン (`<FontAwesomeIcon icon={faGauge} />` 等)
- `<meta name="csrf-token">` を React から読み取って form の `_token` に乗せて POST

### 動作しないときの確認

```bash
# コンテナがすべて Up か
docker compose ps

# Vite ビルド成果物があるか
ls src/public/build/
# manifest.json と assets/ があれば OK
# 無ければ: docker compose exec -e HOME=/tmp app npm run build

# DB にテストアカウントが入っているか
docker compose exec db psql -U laravel laravel -c 'SELECT id, name, email FROM "user"; SELECT id, name, email FROM admin;'

# Laravel ログ (LOG_CHANNEL=stderr なのでファイルではなくコンテナ stdout に出る)
docker compose logs -f app
```

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

## Tinker / artisan 確認系コマンド

DB の中身を見たり、Service / Repository を DI 経由で叩いたり、Job を dispatch したり、
ルートや設定値を確認するための **使い方とコマンド一覧は [`docs/tinker.md`](docs/tinker.md)** に集約。

最短だけここに置く:

```bash
# tinker (対話モード)
docker compose exec -e HOME=/tmp app php artisan tinker

# 1 コマンド実行
docker compose exec -e HOME=/tmp app php artisan tinker --execute='DB::table("user")->count();'

# 最初に打つやつ
docker compose exec app php artisan about           # バージョン / drivers / cache 状態
docker compose exec app php artisan route:list      # 全ルート
docker compose exec app php artisan db:show         # DB 接続情報 + テーブル一覧
docker compose exec app php artisan schedule:list   # 登録された Schedule
docker compose exec app php artisan queue:failed    # 失敗 Job
docker compose exec app php artisan pail            # ログを色付き tail (LOG_CHANNEL=stderr と併用可)
```

> `HOME=/tmp` を渡しているのは、psysh のヒストリ書込先 (`/var/www/.config/psysh`) に
> www-data が書けず warning が出るため。

---

## ログ集約方針 (全コンテナ stdout/stderr 統一)

このプロジェクトは **全コンテナのログを stdout/stderr に出す方針** で統一しています。
AWS ECS では **Fluent Bit (`awsfirelens`) サイドカー** で CloudWatch Logs / S3 / OpenSearch
等に転送する構成を想定。`storage/logs/*.log` のファイルログには書きません。

| コンテナ | ログ出力先 | 設定箇所 |
|---|---|---|
| `web` (nginx) | access_log → `/dev/stdout` / error_log → `/dev/stderr` | `docker/nginx/nginx.conf` |
| `app` (PHP-FPM) | FPM 自体の error_log / access.log / slowlog / worker output 全て stderr | `docker/php/www.conf` |
| `app` (Laravel) | `php://stderr` | `LOG_CHANNEL=stderr` (docker-compose の environment で上書き) |
| `job` / `batch` (supervisord) | 各 program の `stdout_logfile=/dev/stdout` `stderr_logfile=/dev/stderr` | `docker/php/supervisord-{job,batch}.conf` |
| `batch` (cron) | `cron -f -L 4` で stderr、cron job の出力は scheduler-cron.log → `tail -F` で stdout に流す | `docker/php/supervisord-batch.conf` |
| `db` (PostgreSQL) | `log_destination=stderr` / `logging_collector=off` (公式 image デフォルト) | `docker-compose.yml` (postgres command) |

確認:

```bash
docker compose logs -f web    # nginx access / error
docker compose logs -f app    # PHP-FPM + Laravel (LOG_CHANNEL=stderr)
docker compose logs -f job    # queue:work の出力
docker compose logs -f batch  # cron daemon + cron-tail (scheduler 出力)
docker compose logs -f db     # PostgreSQL の query / connection log
```

> 詳細は [`docs/tinker.md`](docs/tinker.md) (Laravel ログを `pail` で見る方法) と
> [`docs/nginx-sidecar.md`](docs/nginx-sidecar.md) (nginx + Fluent Bit 構成の位置づけ) を参照。

---

## ドキュメント

| ファイル | 内容 |
|---|---|
| [`README.md`](README.md) | この文書 (インフラ / セットアップ / 運用) |
| [`src/README.md`](src/README.md) | Laravel アプリ構造 + 認証フローの解説 |
| [`CLAUDE.md`](CLAUDE.md) | コーディング規約 (§1〜§8、AI agent 用にも) |
| [`docs/github-actions.md`](docs/github-actions.md) | GitHub Actions の workflow 解説 + Mermaid シーケンス図 + Secrets / トラブルシューティング |
| [`docs/testing.md`](docs/testing.md) | テスト規約 + 実行方法 + レイヤー別戦略 (Repository / Service / Controller / VO / Job) |
| [`docs/htmx-alpine.md`](docs/htmx-alpine.md) | htmx + Alpine.js のクイックリファレンス + 組み合わせパターン + 実例 + ハマりどころ |
| [`docs/queue.md`](docs/queue.md) | Laravel Queue / Job の使い方 (dispatch / Bus::batch / 失敗処理 / 運用 / テスト) |
| [`docs/schedule.md`](docs/schedule.md) | Laravel タスクスケジュール (cron daemon + schedule:run、頻度指定、Schedule::call/command/job の使い分け) |
| [`docs/nginx-sidecar.md`](docs/nginx-sidecar.md) | nginx をサイドカーで持つ理由 (静的配信 / ログ / 圧縮 / ALB+ECS での位置づけ / 代替構成比較) |
| [`docs/supervisor.md`](docs/supervisor.md) | Supervisor の使い方と運用 (なぜ使うか / conf 構造 / supervisorctl コマンド / job・batch 各コンテナの設定詳細 / ハマりどころ) |
| [`docs/authentication.md`](docs/authentication.md) | Laravel 認証 (このプロジェクトの session 方式 / Blade と SPA それぞれの Vite plugin / 他方式 (Sanctum / Passport / JWT / Socialite) との比較表) |
| [`docs/tinker.md`](docs/tinker.md) | Tinker 活用 + artisan 確認系コマンド (Service / Repository を DI 経由で叩く、`about` / `route:list` / `db:show` / `schedule:list` / `pail` 等) |
| [`.github/CONTRIBUTING.md`](.github/CONTRIBUTING.md) | PR の書き方ガイド |
| [`.github/PULL_REQUEST_TEMPLATE.md`](.github/PULL_REQUEST_TEMPLATE.md) | PR テンプレート (自動挿入) |

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
| 8 | HTTP 層 (Request / Resource) — フィールド定数化、`Arrayable` 直 implements、Controller は薄く |

`.claude/agents/laravel-code-reviewer.md` に、規約違反を検出する確認専用サブエージェントを置いています。
コードを書いた後に「規約レビュー」と頼めば走ります。
