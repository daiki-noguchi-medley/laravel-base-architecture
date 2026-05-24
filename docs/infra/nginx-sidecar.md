# nginx サイドカー構成 (なぜ nginx を別コンテナで持つか)

このプロジェクトは **nginx + php-fpm をそれぞれ別コンテナ** で動かしている。
「同居でも動くのに、なぜ分けるのか」を説明する。

---

## このプロジェクトの構成

```
[Browser]
   ↓ HTTP :8080
┌─────────────────────────────┐
│  web (nginx)                │
│   ├─ 静的 (CSS/JS/画像/SVG) │
│   ├─ gzip + Brotli 圧縮     │
│   ├─ セキュリティヘッダ      │
│   ├─ access.log             │
│   └─ /*.php  →  fastcgi     │─ tcp :9000 ─┐
└─────────────────────────────┘             │
                                            ▼
                              ┌─────────────────────────┐
                              │  app (php-fpm)          │
                              │   ├─ Laravel framework  │
                              │   ├─ Service/Repository │
                              │   └─ DB connection      │
                              └─────────────────────────┘
                                            ↕
                              ┌─────────────────────────┐
                              │  db (PostgreSQL 16)     │
                              └─────────────────────────┘
```

---

## なぜ nginx をサイドカーとして分けるか

### 1. 静的コンテンツを php-fpm に通さない (パフォーマンス)

php-fpm はリクエストごとに **PHP プロセスを動かす** (or 既存ワーカーで処理) ためのもの。
静的ファイルを返すのにも PHP の起動コスト / メモリが乗ってくる。

nginx は **`sendfile` + event-driven** で静的ファイル配信が圧倒的に速い:

| 配信方法 | おおよその性能 |
|---|---|
| nginx 単体で静的ファイル | 数万〜数十万 req/s |
| php-fpm 経由で静的ファイル | 数千 req/s (PHP の bootstrap が入る) |

`/build/assets/*.js` のような **contenthash 付きの長期 cache 静的アセット** を php-fpm に通すのは
リソースの無駄。nginx で `try_files` してそのまま返す。

### 2. ログを役割別に分離

| ログ | 場所 | 役割 |
|---|---|---|
| nginx **access.log** | `web` コンテナの stdout | 全リクエストの記録 (静的 + 動的) — レスポンスタイム / status / UA / Referer |
| nginx **error.log** | `web` コンテナの stderr | nginx 自体のエラー (404 / 5xx / アクセス権限等) |
| php-fpm **error.log** | `app` コンテナの stdout | PHP のエラー / fatal / segfault |
| php-fpm **slow log** | `app` コンテナ内 | 遅いリクエスト (任意設定) |
| Laravel **laravel.log** | `app:storage/logs/` | アプリケーションログ (`Log::info` 等) |

**監視 / 障害切り分けの観点**:

- HTTP 500 が増えた → nginx access.log で発生時刻 / URL を絞る → php-fpm error.log で例外 stacktrace を見る
- ページが遅い → nginx access.log で平均レスポンスタイム → php-fpm slow log で遅い処理を特定
- 攻撃検知 → nginx access.log の UA / status コード分布

これらを **コンテナ単位の stdout** で扱えると、CloudWatch Logs / Datadog / Loki 等への
転送設定が単純化する (`logDriver: awslogs` をコンテナごとに別 stream で)。

### 3. 圧縮 (gzip / Brotli) は nginx の仕事

php-fpm でも `ob_gzhandler` で圧縮できるが、**毎リクエスト PHP で圧縮するのは無駄**。
nginx の Brotli/gzip は C 実装で速く、ヘッダ判定 (`Accept-Encoding`) も nginx が担う。

このプロジェクトでは `docker/nginx/nginx.conf` で gzip + Brotli を有効化済み (16% まで縮小実測)。

### 4. セキュリティヘッダの一括付与

Laravel の middleware でも書けるが、**全レスポンス (404 / 5xx / 静的ファイル含む) に同じヘッダを付ける**
には nginx の `add_header always` が確実。`docker/nginx/security-headers.conf` 参照。

### 5. HTTPS / HTTP/2 終端 (本番想定)

開発環境は HTTP のみだが、本番では:

- **nginx 単独運用**: nginx が TLS 終端 + HTTP/2、php-fpm は HTTP1.1 のままで OK
- **ALB の背後の nginx**: ALB で TLS 終端、nginx は HTTP のまま (内部 VPC は平文 or 別途暗号化)

php-fpm に SSL の責務を持たせる必然性はない (むしろ持たせるべきでない)。

### 6. デプロイ / ロール変更の独立性

- nginx の設定変更 (gzip パラメータ / ヘッダ追加) は **app コンテナを止めずに反映可能**
- PHP のバージョンアップは web を止めずに app だけ build → recreate
- 障害切り分け: 「web が落ちている → ALB 配下から外れる」「app が落ちている → 502 を nginx が返す」が明確

---

## AWS ALB / ECS 環境での位置づけ

```
┌────────────┐
│ Route 53   │  *.example.com → ALB
└─────┬──────┘
      │
      ▼
┌────────────┐
│ ALB        │  HTTPS :443 終端、Health check (/up)
│            │  Target Group → ECS Service
└─────┬──────┘
      │ HTTP :80 (VPC 内)
      ▼
┌─────────────────────────────────────────────────┐
│ ECS Task (1 サービス、複数コンテナ同居)         │
│                                                 │
│   ┌──────────┐   localhost   ┌──────────┐      │
│   │ web      │ ─ tcp:9000 ─> │ app      │      │
│   │ nginx    │               │ php-fpm  │      │
│   │ (sidecar)│               │          │      │
│   └──────────┘               └──────────┘      │
│                                                 │
│   ┌──────────────────────────────────────┐     │
│   │ (Sidecar として cloudwatch agent /   │     │
│   │  datadog agent / xray daemon 等も    │     │
│   │  同居しうる)                          │     │
│   └──────────────────────────────────────┘     │
└─────────────────────────────────────────────────┘

         ─────────────────────────
         job / batch は別 ECS タスク
         (バックグラウンド処理、ALB 経由しない)
         ─────────────────────────

┌────────────────┐  ┌────────────────┐
│ RDS PostgreSQL │  │ CloudWatch     │
│                │  │ Logs / Metrics │
└────────────────┘  └────────────────┘
```

### ALB がいても nginx は必要か → Yes

ALB は L7 ロードバランサだが、**以下ができない**:

| 機能 | ALB | nginx |
|---|---|---|
| 静的ファイル配信 | ✗ (S3 + CloudFront に逃がす) | ✓ |
| fastcgi で php-fpm に転送 | ✗ | ✓ |
| Brotli 圧縮 | ✗ (gzip のみ) | ✓ |
| 細かいセキュリティヘッダ制御 | △ (限定的) | ✓ |
| URL rewrite / fallback (`try_files`) | △ (Listener Rule で限定的) | ✓ |
| 設定の細かいチューニング | △ (AWS マネジメントの範囲) | ✓ (`nginx.conf` で自由) |
| TLS 終端 | ✓ (証明書管理楽) | ✓ |
| L7 ヘルスチェック | ✓ | ✓ |
| 重み付けルーティング / canary | ✓ | △ (upstream で可能だが面倒) |

→ **ALB で TLS 終端 + ルーティング、nginx で静的配信 + fastcgi + 圧縮** の組み合わせが自然。

### ECS タスク定義 (抜粋例)

```jsonc
{
  "containerDefinitions": [
    {
      "name": "web",
      "image": "...laravel-base:web",
      "essential": true,
      "portMappings": [{ "containerPort": 80 }],
      "links": [],
      "dependsOn": [{ "containerName": "app", "condition": "START" }],
      "logConfiguration": {
        "logDriver": "awslogs",
        "options": {
          "awslogs-group": "/ecs/laravel-base",
          "awslogs-stream-prefix": "web"
        }
      }
    },
    {
      "name": "app",
      "image": "...laravel-base:app",
      "essential": true,
      "portMappings": [{ "containerPort": 9000 }],
      "logConfiguration": {
        "logDriver": "awslogs",
        "options": {
          "awslogs-group": "/ecs/laravel-base",
          "awslogs-stream-prefix": "app"
        }
      }
    }
  ]
}
```

ポイント:
- **同一タスク内で localhost (`localhost:9000`) 通信** → docker compose の `app:9000` が `localhost:9000` に変わるだけ
  (nginx 設定の `fastcgi_pass` だけ書き換える)
- **awslogs-stream-prefix を web / app で分ける** → CloudWatch Logs Insights で個別に検索可能
- **dependsOn** で app の起動を待ってから web を起動

job / batch は別タスク (バックグラウンド処理は ALB 経由しないため、サイドカーにする必要なし)。

---

## ログの観点 (本番運用)

### nginx access.log を構造化して送る

`nginx.conf` の `log_format` を JSON にすると CloudWatch Logs Insights / Athena で扱いやすい:

```nginx
log_format json escape=json
  '{'
    '"time":"$time_iso8601",'
    '"remote_addr":"$remote_addr",'
    '"request":"$request",'
    '"status":$status,'
    '"body_bytes":$body_bytes_sent,'
    '"request_time":$request_time,'
    '"upstream_time":"$upstream_response_time",'
    '"ua":"$http_user_agent",'
    '"referer":"$http_referer"'
  '}';

access_log /var/log/nginx/access.log json;
```

CloudWatch Logs Insights クエリ例:

```
fields @timestamp, status, request, request_time
| filter status >= 500
| stats count(*) by status
```

### php-fpm slow log

`docker/php/www.conf` で:

```ini
request_slowlog_timeout = 5s
slowlog = /proc/self/fd/2     ; stdout
```

→ 5 秒超のリクエストが stack trace 付きで stdout に出る → CloudWatch Logs へ。

### Laravel ログ

`config/logging.php` で stack driver の中身を stdout に向ける (本番のみ):

```php
'stack' => [
    'driver' => 'stack',
    'channels' => explode(',', env('LOG_STACK', 'stderr')),
],
'stderr' => [
    'driver' => 'monolog',
    'handler' => StreamHandler::class,
    'with' => ['stream' => 'php://stderr'],
],
```

→ Laravel のログも CloudWatch Logs に行く。

---

## 代替構成との比較

| 構成 | Pros | Cons |
|---|---|---|
| **nginx サイドカー (このプロジェクト)** | 責務分離、静的高速、ログ独立、ALB と相性、PHP 単体テスト容易 | コンテナ数 +1 |
| nginx + php-fpm を 1 コンテナ | コンテナ少、デプロイ単純 | プロセス管理が複雑 (supervisor 必要)、責務不明確、片方の trouble で巻き込み |
| php-fpm 単体 + ALB | 一番シンプル | 静的が php-fpm 経由で遅い、Brotli なし、細かい header 制御不可 |
| Roadrunner / Octane / FrankenPHP | 高速 (常駐 PHP、起動コスト 0) | Laravel コード側に制約 (状態保持の罠)、デバッグ難、ECS の workflow 変更 |
| nginx + nginx-unit | 軽量、設定が JSON | 採用例少、学習コスト高 |
| Caddy + php-fpm | TLS 自動、設定簡単 | nginx ほど枯れていない、Brotli 標準対応だが拡張弱 |

このプロジェクトでは:

- **「分けるのが普通」** (Web 業界の標準パターン、トラブルシュート資料が豊富)
- **「ECS でも自然」** (サイドカー = ECS タスク定義の標準概念)
- **「学習コスト低」** (nginx の知識が直接活きる)

という理由で nginx サイドカー構成を採用。

---

## 関連ファイル

| ファイル | 役割 |
|---|---|
| [`docker-compose.yml`](../../docker-compose.yml) の `web` サービス | コンテナ定義 |
| [`docker/nginx/Dockerfile`](../../docker/nginx/Dockerfile) | Alpine 公式 nginx + brotli モジュール |
| [`docker/nginx/nginx.conf`](../../docker/nginx/nginx.conf) | main config (gzip / brotli / log_format) |
| [`docker/nginx/default.conf`](../../docker/nginx/default.conf) | vhost (root / fastcgi_pass / 静的 long cache) |
| [`docker/nginx/security-headers.conf`](../../docker/nginx/security-headers.conf) | セキュリティヘッダ snippet |

## 関連 doc

- 圧縮詳細 (gzip / Brotli): [`../docker/nginx/nginx.conf`](../../docker/nginx/nginx.conf) のコメント
- インフラ全体: [`../README.md`](../../README.md)
- 規約: [`../CLAUDE.md`](../../CLAUDE.md)
