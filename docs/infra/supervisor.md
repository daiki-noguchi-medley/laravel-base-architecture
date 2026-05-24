# Supervisor (queue / scheduler の起動と監視)

`job` コンテナ (queue:work) と `batch` コンテナ (cron daemon) では、PID 1 として
**Supervisor** を動かし、その配下で実プロセスを管理している。
このドキュメントでは Supervisor の設定と運用方法を解説する。

- 規約・全体設計: [`../CLAUDE.md §7`](../../CLAUDE.md)
- Queue / Job の使い方: [`queue.md`](../queue.md)
- Schedule の使い方: [`schedule.md`](../schedule.md)

---

## なぜ Supervisor を使うか

### Docker の `restart: unless-stopped` だけでは足りない

| 項目 | Docker restart のみ | Supervisor + Docker restart |
|---|---|---|
| プロセスが落ちたとき | コンテナ全体を再起動 (5〜10s) | プロセスだけ即時再起動 (1s 以内) |
| プロセス単位の制御 | できない | `supervisorctl restart queue-worker` 等で個別 |
| 1 コンテナ内の複数プロセス | 1 つしか起動できない (PID 1 が死ぬとコンテナごと終了) | 複数プロセス同居可 (例: cron + tail) |
| `--max-time` / `--memory` の自己終了からの再起動 | コンテナごと再起動 | プロセスだけ再起動 |
| プロセスの状態確認 | `docker ps` だけ | `supervisorctl status` で個別 |

Laravel 公式も **Production では Supervisor 推奨** ([公式ドキュメント](https://laravel.com/docs/queues#supervisor-configuration))。

### コンテナ統合は Docker の "1 コンテナ 1 プロセス" 原則とどう折り合うか

- **PID 1 = supervisord** が「単一のメインプロセス」とみなせる
- supervisord の子プロセスは supervisord と一体 (kill されたら supervisord が再起動)
- Docker から見るとあくまで `supervisord` 1 つを動かしているだけ
- これは Laravel 公式 / Docker 公式ともに認める運用パターン

---

## このプロジェクトの構成

| コンテナ | supervisord.conf | 主要 program |
|---|---|---|
| `job` | [`docker/php/supervisord-job.conf`](../../docker/php/supervisord-job.conf) | `queue-worker` (1 つ) |
| `batch` | [`docker/php/supervisord-batch.conf`](../../docker/php/supervisord-batch.conf) | `cron` + `cron-tail` (2 つ) |

両方とも:
- `apk add supervisor` で `/usr/bin/supervisord` が入る (Dockerfile)
- `command: supervisord -c /etc/supervisor/conf.d/supervisord.conf -n` で起動
- `-n` で foreground 起動 (Docker の PID 1 に張り付ける)
- bind mount で各 conf ファイルを `/etc/supervisor/conf.d/supervisord.conf` に注入

---

## 設定ファイルの中身

### 共通ヘッダ (どの conf にも入る)

```ini
[supervisord]
nodaemon=true                  ; foreground 起動 (Docker の PID 1)
user=root                      ; supervisord 自体は root で動かす (program 内で user 切替)
logfile=/dev/null              ; supervisord 自体のログは捨てる (program のログだけ見たい)
logfile_maxbytes=0
pidfile=/tmp/supervisord.pid

; supervisorctl から繋ぐための unix socket
[unix_http_server]
file=/var/run/supervisor.sock
chmod=0700

[supervisorctl]
serverurl=unix:///var/run/supervisor.sock

[rpcinterface:supervisor]
supervisor.rpcinterface_factory = supervisor.rpcinterface:make_main_rpcinterface
```

> ⚠️ `[unix_http_server]` を書かないと `supervisorctl status` 等が **`no such file` エラー** で動かない。
> 必須セクション。

### `[program:xxx]` セクションの主要オプション

```ini
[program:queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --queue=default --tries=3 --backoff=10 --max-time=3600 --memory=256 --sleep=3
user=www-data                  ; プロセスの実行ユーザ
autostart=true                 ; supervisord 起動時に自動起動
autorestart=true               ; プロセス終了時に自動再起動 (true / unexpected / false)
numprocs=1                     ; 並列起動するプロセス数
stopasgroup=true               ; SIGTERM を子プロセス群にも送る
killasgroup=true               ; SIGKILL も同上
stopwaitsecs=60                ; SIGTERM 後、SIGKILL までの猶予 (実行中の Job を完走させたい)
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0      ; /dev/stdout に書く時はローテーションしない
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

#### 重要オプション解説

| オプション | 意味 / 注意 |
|---|---|
| `autostart=true` | supervisord 起動と同時に program 起動。手動で start したいなら false |
| `autorestart=true` | 終了したら必ず再起動 (`unexpected` だと exitcodes に書いた終了コード以外の場合だけ再起動) |
| `numprocs=N` | N 並列起動。各プロセスは `process_name` で区別 (`queue-worker_00`, `_01`, ...) |
| `stopasgroup=true` | SIGTERM を process group 全体に送る (queue:work が fork する worker にも届く) |
| `killasgroup=true` | SIGKILL も同様 |
| `stopwaitsecs=60` | SIGTERM 送信 → SIGKILL までの猶予。queue:work は実行中 Job を完走させたいので長め (60〜120s) |
| `stdout_logfile=/dev/stdout` | コンテナの stdout に流す → `docker compose logs` で見える |
| `stdout_logfile_maxbytes=0` | `/dev/stdout` 等の特殊ファイルでローテーションしない設定 |

---

## `job` コンテナの設定

`docker/php/supervisord-job.conf`:

```ini
[program:job-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --queue=default --tries=3 --backoff=10 --max-time=3600 --memory=256 --sleep=3
user=www-data
autostart=true
autorestart=true
numprocs=1
stopasgroup=true
killasgroup=true
stopwaitsecs=60
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

### `queue:work` のオプション

| オプション | 値 | 意味 |
|---|---|---|
| `--queue=default` | default | 監視する queue 名 (Bus::batch の Job も default queue に来る) |
| `--tries=3` | 3 | 失敗時の最大試行回数 |
| `--backoff=10` | 10 秒 | 再試行までの待機 |
| `--max-time=3600` | 1 時間 | 起動から 1 時間で自己終了 → supervisord が再起動 (フレッシュ状態を保つ、メモリリーク対策) |
| `--memory=256` | 256 MB | メモリ超過で自己終了 → 再起動 |
| `--sleep=3` | 3 秒 | 待機中 Job がないときの poll 間隔 |

### 並列数を増やしたいとき

```ini
numprocs=4   ; 4 並列
```

→ `queue-worker_00`, `_01`, `_02`, `_03` の 4 プロセスが起動。
スループットが 4 倍 (DB 接続数 / メモリも 4 倍消費に注意)。

---

## `batch` コンテナの設定

`docker/php/supervisord-batch.conf`:

```ini
; ① cron daemon (PID 7) — 毎分 /etc/cron.d/laravel-scheduler を発火
[program:cron]
command=/usr/sbin/cron -f -L 4    ; -f = foreground、-L 4 = log level (job 開始/終了を syslog に)
user=root                          ; cron daemon は root 必須 (各 job 内で www-data に切り替え)
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

; ② cron-tail (PID 8) — cron job 出力 (storage/logs/scheduler-cron.log) を stdout に流す
[program:cron-tail]
command=/bin/sh -c "mkdir -p /var/www/html/storage/logs && touch /var/www/html/storage/logs/scheduler-cron.log && chown www-data:www-data /var/www/html/storage/logs/scheduler-cron.log && exec tail -F /var/www/html/storage/logs/scheduler-cron.log"
user=root
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

### なぜ cron-tail program が必要か

- cron job の `schedule:run` の標準出力は `/proc/1/fd/1` (supervisord の stdout) に直接書こうとすると
  **www-data からは書けない** (root 所有のため Permission denied)
- 一旦 `storage/logs/scheduler-cron.log` に書いて、別 program で `tail -F` して stdout に流す形にしている
- これで `docker compose logs batch -f` から cron の発火状況が見える

### cron daemon の補足

- `cron -f`: foreground 起動 (`-` を付けないと daemon 化して supervisord が監視できない)
- `cron -L 4`: ログレベル (1=start, 2=end, 4=start+end, 8=info, 15=all)
- crontab 本体は bind mount で `/etc/cron.d/laravel-scheduler` に配置 (`docker/cron/laravel-scheduler`)

---

## supervisorctl コマンド

コンテナ内で実行:

```bash
docker compose exec job   supervisorctl status
docker compose exec batch supervisorctl status
```

### よく使うコマンド

| コマンド | 役割 |
|---|---|
| `status` | 全 program の状態を表示 (`RUNNING` / `STOPPED` / `FATAL` / `STARTING`) |
| `status <program>` | 特定 program のみ |
| `start <program>` | 起動 |
| `stop <program>` | 停止 (`stopwaitsecs` 後に強制 kill) |
| `restart <program>` | 停止 → 起動 (config 変更を反映したいとき) |
| `reread` | conf ファイルを読み直す (変更検出)。**新規 program は表示されない** |
| `update` | reread + 差分の add/remove/restart を実行 (実反映はこちら) |
| `tail <program>` | 直近の stdout を表示 |
| `tail -f <program>` | follow |
| `pid <program>` | プロセス PID 表示 |

### 例: queue-worker のコード変更を反映したい

```bash
# (1) コードを edit
vi src/Demo/Service/User/UserAuthServiceImpl.php

# (2) queue-worker は memory に古いコードを持っているので再起動
docker compose exec job supervisorctl restart job-worker
```

### 例: 新しい program を supervisord.conf に追加した

```bash
# (1) supervisord-job.conf を編集して [program:another-worker] を追加
# (2) Dockerfile で COPY しているので image 再ビルド
docker compose build job

# (3) コンテナ再作成
docker compose up -d --force-recreate job

# (4) status 確認
docker compose exec job supervisorctl status
```

> 💡 もし conf を bind mount にしていれば `reread` + `update` だけで反映可能。
> このプロジェクトは `bind mount` 採用なので、conf 編集後は:

```bash
docker compose exec job supervisorctl reread
docker compose exec job supervisorctl update
```

---

## status コマンドの読み方

```
$ docker compose exec job supervisorctl status
job-worker:job-worker_00     RUNNING   pid 7, uptime 1:23:45

$ docker compose exec batch supervisorctl status
cron                         RUNNING   pid 7, uptime 1:23:45
cron-tail                    RUNNING   pid 8, uptime 1:23:45
```

| 状態 | 意味 |
|---|---|
| `RUNNING` | 正常稼働中 |
| `STARTING` | 起動中 (`startsecs` 秒以内、まだ RUNNING に昇格していない) |
| `STOPPED` | 手動 / プログラム終了で停止 |
| `BACKOFF` | 起動失敗の cool-down 中 (繰り返すと FATAL へ) |
| `FATAL` | 起動を繰り返し失敗、もうリトライしない |
| `EXITED` | 終了 (autorestart=false の場合のみ表示) |
| `UNKNOWN` | 状態不明 (バグ or socket 問題) |

### FATAL になったとき

```bash
# stderr / stdout を見る
docker compose exec job supervisorctl tail -f job-worker stderr

# config を直してから restart で復活
docker compose exec job supervisorctl restart job-worker
```

---

## デプロイ時の流れ (queue / batch コンテナ)

### Queue コードを変更したとき

```bash
# 1. コード変更を bind mount で反映 (実コードは src/ にあるのですぐ反映される)
# 2. ただし queue-worker は memory に古いコードを持っているので restart
docker compose exec job supervisorctl restart job-worker
```

### Schedule (crontab) を変更したとき

```bash
# 1. docker/cron/laravel-scheduler を編集
vi docker/cron/laravel-scheduler

# 2. cron daemon に SIGHUP を送る or restart
docker compose exec batch supervisorctl restart cron
```

### supervisord.conf 自体を変更したとき

```bash
# 1. docker/php/supervisord-{job,batch}.conf を編集
# 2. 上で書いた通り、bind mount なら reread + update
docker compose exec job   supervisorctl reread
docker compose exec job   supervisorctl update
docker compose exec batch supervisorctl reread
docker compose exec batch supervisorctl update
```

---

## ハマりどころ

| 症状 | 原因 | 対処 |
|---|---|---|
| `supervisorctl status` が `no such file` | `[unix_http_server]` セクションが無い | 共通ヘッダ通り 3 セクション (unix_http_server / supervisorctl / rpcinterface) を書く |
| supervisord が即終了 | `nodaemon=true` を書いていない | `[supervisord]` に `nodaemon=true` 必須 |
| プロセスが永遠に BACKOFF | command が間違い / 実行ファイル無し | `supervisorctl tail <name> stderr` で stderr 確認 |
| `stdout_logfile_maxbytes` エラー | `/dev/stdout` への書込で size_rotation を有効にしてる | `stdout_logfile_maxbytes=0` を明示 |
| 停止に時間がかかる | queue:work が長時間 Job を実行中 | `stopwaitsecs` を伸ばす (推奨 60〜120s)。`killasgroup=true` も忘れず |
| queue:work のメモリが膨れ続ける | Laravel の query / event の循環参照 | `--max-time=3600` + `--memory=256` で定期的に self-restart (既に設定済) |
| 並列起動したいのに `numprocs=4` で 1 個しか起動しない | `process_name=%(program_name)s_%(process_num)02d` を書いていない | 共通ヘッダ通り書く (`numprocs > 1` のときは必須) |
| cron が動いてるのに schedule が発火しない | crontab のファイル末尾に改行が無い (Debian cron) | 末尾改行を必ず入れる |
| `docker compose restart` で conf 変更が反映されない | Dockerfile で COPY していて bind mount じゃない | `docker compose build` で再ビルド or bind mount に変更 |

---

## 関連

- 規約 (Job 配置 / Schedule 配置 / 鉄則): [`../CLAUDE.md §7`](../../CLAUDE.md)
- Queue / Job の使い方: [`queue.md`](../queue.md)
- Schedule の使い方: [`schedule.md`](../schedule.md)
- インフラ (nginx サイドカー): [`nginx-sidecar.md`](./nginx-sidecar.md)
- GitHub Actions: [`github-actions.md`](./github-actions.md)
- Supervisor 公式: <http://supervisord.org/configuration.html>
- Laravel Queue + Supervisor: <https://laravel.com/docs/queues#supervisor-configuration>
