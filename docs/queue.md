# Queue / Job (非同期処理)

Laravel の Queue 機能の使い方。配置・命名規約は [`../CLAUDE.md §7`](../CLAUDE.md) を参照。
このドキュメントは **how-to (使い方)** にフォーカス。

---

## 概要

ユーザーリクエストに対して **時間がかかる処理** (メール送信、外部 API 呼び出し、CSV import など) を
レスポンスから切り離して **非同期で実行** する仕組み。

```
[ユーザー] HTTP request
   ↓
[Controller / Service]
   ├─ SomeJob::dispatch($payload)   ← jobs テーブルに INSERT (即返る)
   ↓
[ユーザー] HTTP response (高速)


別プロセス (job コンテナ) で:
[supervisord]
   └─ [queue:work]
         ↓ poll
       [jobs テーブル]
         ↓ 取り出し
       SomeJob::handle()   ← 実際の処理 (Service に委譲)
```

---

## このプロジェクトの構成

| 項目 | 値 |
|---|---|
| Queue ドライバー | `database` (`src/.env` の `QUEUE_CONNECTION=database`) |
| Worker | `job` コンテナ内で **supervisord → queue:work** |
| 設定ファイル | `docker/php/supervisord-job.conf` |
| Queue 名 | **`default` 1 本** (Bus::batch も含めて全部 default queue で処理) |
| テーブル | `jobs` / `failed_jobs` / `job_batches` (Laravel 標準 migration 済み) |

> ⚠️ `batch` コンテナは **時刻トリガ (cron daemon)** であって Queue worker ではない。
> `Bus::batch()` の Job も `job` コンテナで処理される。詳細は [`schedule.md`](./schedule.md)。

---

## Job クラスを作る

### artisan で雛形生成

```bash
docker compose exec app php artisan make:job SendWelcomeMailJob
# → src/app/Jobs/SendWelcomeMailJob.php
```

### このプロジェクトの規約 (CLAUDE.md §7) に沿った形

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use Demo\User\Service\UserAuthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendWelcomeMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;          // 失敗時の再試行回数
    public int $backoff = 10;       // 再試行までの待機秒
    public int $timeout = 60;       // 1 回の実行の最大秒数

    public function __construct(
        public readonly int $userId,         // プリミティブ or readonly DTO のみ
    ) {}

    public function handle(UserAuthService $userAuthService): void
    {
        // Service に委譲するだけ (条件分岐 / DB / API 通信を書かない)
        $userAuthService->sendWelcomeMail($this->userId);
    }
}
```

ポイント (CLAUDE.md §7 §4 に準拠):

- `handle()` は **Service 呼び出し 1 行** が基本
- コンストラクタ引数は **プリミティブ / readonly DTO のみ** (Eloquent モデルは渡さない)
- Job は **冪等** に設計 (リトライで二重実行されてもデータが壊れないように)

---

## dispatch する

### 基本

```php
SendWelcomeMailJob::dispatch($userId);
```

→ jobs テーブルに INSERT。Controller / Service の処理は **即座に返る**。
worker が拾って実行するのは別タイミング (秒オーダー)。

### dispatch の派生

```php
// 遅延 (10 秒後に実行)
SendWelcomeMailJob::dispatch($userId)->delay(now()->addSeconds(10));

// 特定 connection
SendWelcomeMailJob::dispatch($userId)->onConnection('redis');

// 特定 queue (このプロジェクトは default 一本だが将来用)
SendWelcomeMailJob::dispatch($userId)->onQueue('heavy');

// 同期実行 (テストや CLI 一発実行で)
SendWelcomeMailJob::dispatchSync($userId);

// chain (順番に実行)
Bus::chain([
    new ProcessVideoJob($videoId),
    new GenerateThumbnailJob($videoId),
    new NotifyUserJob($userId),
])->dispatch();
```

---

## Bus::batch (複数 Job のグルーピング)

複数 Job を 1 単位として **進捗管理 / 一括キャンセル** したいときに使う。

```php
use App\Jobs\ImportUserCsvJob;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

$batch = Bus::batch([
    new ImportUserCsvJob('chunk-1.csv'),
    new ImportUserCsvJob('chunk-2.csv'),
    new ImportUserCsvJob('chunk-3.csv'),
])
->then(fn (Batch $b) => /* 全 Job 成功 */)
->catch(fn (Batch $b, \Throwable $e) => /* 1 つでも失敗 */)
->finally(fn (Batch $b) => /* 完了 (成功 / 失敗どちらも) */)
->name('user import')
->dispatch();

// dispatch 後、$batch->id で進捗を引ける
$progress = Bus::findBatch($batch->id);
echo "{$progress->processedJobs()} / {$progress->totalJobs}";
```

事前準備 (初回のみ、`job_batches` テーブルが必要):

```bash
docker compose exec app php artisan make:queue-batches-table
docker compose exec app php artisan migrate
```

Job 側で `Batchable` trait を入れておくと、`$this->batch()` で親 batch にアクセスできる
(通常 dispatch では null になるだけで両対応可能):

```php
use Illuminate\Bus\Batchable;

final class ImportUserCsvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;   // batch が cancel されていればスキップ
        }
        // ...本処理
    }
}
```

---

## 失敗時の挙動

### 自動リトライ

- `$tries = 3` なら **最大 3 回** 実行 (初回 + 再試行 2 回)
- 失敗ごとに `$backoff` 秒待ってから再試行
- すべての試行に失敗したら **`failed_jobs` テーブルに格納**

### 失敗を確認 / 再実行 / 削除

```bash
# 失敗 Job の一覧
docker compose exec app php artisan queue:failed

# 特定の失敗 Job を再キュー
docker compose exec app php artisan queue:retry {uuid}

# 全失敗 Job を再キュー
docker compose exec app php artisan queue:retry all

# 失敗 Job を全削除 (再実行せず捨てる)
docker compose exec app php artisan queue:flush
```

### handle() 内で明示的に失敗させる

```php
public function handle(): void
{
    if ($somethingWrong) {
        throw new \RuntimeException('外部 API が 5xx');
    }
}
```

→ 例外を投げると失敗扱い。`$tries` までリトライされる。

### リトライしたくない例外

```php
use Illuminate\Bus\UniqueLock;

public function handle(): void
{
    if ($alreadyProcessed) {
        $this->fail('既に処理済み');   // failed_jobs に直行 (リトライしない)
    }
}
```

---

## 運用コマンド

### Job 状態の観察

```bash
# 待機中の Job 件数
docker compose exec -T db psql -U laravel laravel -c "SELECT count(*) FROM jobs;"

# 失敗 Job
docker compose exec -T db psql -U laravel laravel -c "SELECT id, queue, attempts, failed_at FROM failed_jobs ORDER BY failed_at DESC LIMIT 10;"

# Batch 進捗
docker compose exec -T db psql -U laravel laravel -c "SELECT id, name, total_jobs, processed_jobs, failed_jobs, cancelled_at FROM job_batches ORDER BY created_at DESC LIMIT 5;"
```

### Supervisor 配下の worker プロセス

```bash
docker compose exec job supervisorctl status
#   job-worker:job-worker_00     RUNNING   pid 7, uptime 0:42:13
```

### Worker の再起動 (デプロイ後、コード変更を反映)

```bash
# Supervisor 経由で graceful restart
docker compose exec job supervisorctl restart job-worker
```

Worker は **起動時のコードを memory に持っている** ので、コード変更後は再起動が必須。
`--max-time=3600` で 1 時間ごとに自己終了 → supervisord が再起動するので、最悪 1 時間で反映される。

### 1 回だけ実行 (デバッグ用)

```bash
# jobs テーブルから 1 件取って実行して終了
docker compose exec app php artisan queue:work --once

# tries / verbose を指定
docker compose exec app php artisan queue:work --once --tries=1 -v
```

---

## Job のテスト

`Bus::fake()` / `Queue::fake()` で実行をスタブ化し、**dispatch されたか** だけを確認できる。

```php
use Illuminate\Support\Facades\Bus;

public function test_register_dispatches_welcome_mail_job(): void
{
    Bus::fake();

    $this->post('/register', [
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => 'secret',
    ])->assertRedirect();

    Bus::assertDispatched(SendWelcomeMailJob::class, fn ($job) => $job->userId === 1);
    Bus::assertNotDispatched(SendInvoiceJob::class);
}
```

Job 単体 (`handle()`) のテストは Service と同じく **Repository を mock** して Unit Test
([`testing.md`](./testing.md) 参照)。

---

## 定期的に発火するジョブの作法

「定期発火 → 対象を拾って処理する」形のジョブでは、**重なり・打ち切り・無言の 0 件**が必ず問題になる。
規約は [`../CLAUDE.md §7`](../CLAUDE.md) が一次情報。ここではこのリポジトリの実際の値を並べる。

| 決めること | このリポジトリでの値 | 理由 |
|---|---|---|
| `$tries` | **1** | 再試行は次の発火に委ねる。キュー側でも再試行すると同じ対象を二重に処理し得る |
| `$timeout` | **60 秒未満** | `retry_after` (`config/queue.php` = **90 秒**) と `stopwaitsecs` (`docker/php/supervisord-job.conf` = **60 秒**) の **両方より短く**。前者を超えると走っている間に予約が切れて別のワーカーが同じ処理を取り、後者を超えるとコンテナ停止時に強制終了される |
| 重なりの防止 | **`Cache::lock`** | 下記 |
| 1 回で処理する件数 | **上限を切る** | 下記 |
| ログ | **0 件でも残す** (`Log::channel('stdout')`) | 下記 |

**`ShouldBeUnique` は使わない。**
ロックが取れないと dispatch を「例外も出さず、何も積まず」に打ち切るため
(`PendingDispatch::shouldDispatch`)、**投げた側から取りこぼしを検知できない**。
打ち切りでワーカーが強制終了してロックだけ残ると、
「発火しているのにジョブが 1 度も走らず、0 件のログすら出ない」周期が生まれる。

**ロックの寿命は「打ち切り (`$timeout`) より少し長く、発火の間隔より短く」。**
強制終了で release が呼ばれなくても、次の発火までに自然に切れる必要がある。

**1 回で通し切れる量に上限を切る。**
Service が渡された件数を全部処理し終えてから返す作りだと、打ち切りに達した回は
後片付けに 1 度も到達せず **1 件も進まない**。次回も同じ先頭を取り直すので永久に進まなくなる。

**0 件でもログを残す。**
「発火はしているが 1 件も進んでいない」を外から見分けるため。
何も出さないと、正常な «対象なし» と «壊れていて 0 件» が区別できない。

**秘密情報をペイロードに載せるなら `ShouldBeEncrypted`。**
`jobs.payload` にも、失敗後に残る `failed_jobs.payload` にも **平文で書かれる**。
ジョブ内で例外を握り潰しても防げない (積んだ時点で書かれている)。

```php
final class SyncExternalStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;    // 再試行は次の発火に委ねる
    public int $timeout = 50; // retry_after(90) と stopwaitsecs(60) の両方より短く

    public function handle(ExternalStatusService $service): void
    {
        // 打ち切り(50 秒)より少し長く、発火間隔(5 分)より短い寿命にする
        $lock = Cache::lock('sync-external-status', 90);
        if (! $lock->get()) {
            Log::channel('stdout')->info('前回の実行と重なったため見送りました');
            return;
        }

        try {
            $count = $service->syncPendingList(limit: Limit::SYNC_BATCH_SIZE);
            Log::channel('stdout')->info("外部状態を同期しました: {$count} 件");
        } finally {
            $lock->release();
        }
    }
}
```

---

## ハマりどころ

| 症状 | 原因 | 対処 |
|---|---|---|
| Job が処理されない | worker が動いていない | `docker compose exec job supervisorctl status` 確認 |
| **発火しているのにジョブが 1 度も走らず、0 件のログすら出ない** | `ShouldBeUnique` のロックが残ったまま (dispatch が無言で打ち切られる) | `Cache::lock` を自分で取り、**取れなかったことをログに残す** |
| **毎回同じ先頭から取り直して永久に進まない** | 1 回で処理する件数に上限が無く、打ち切りで後片付けへ到達していない | 1 回で通し切れる上限を切る |
| **同じ対象を 2 つのワーカーが同時に処理する** | `$timeout` が `retry_after` (90 秒) を超えている | `$timeout` を 60 秒未満にする |
| **コンテナ停止のたびにジョブが途中で死ぬ** | `$timeout` が `stopwaitsecs` (60 秒) を超えている | 同上 |
| **失敗したジョブの payload に秘密情報が残っている** | `failed_jobs.payload` は平文 | `ShouldBeEncrypted` を付ける |
| コード変更が反映されない | worker が古いコードを memory に持っている | `supervisorctl restart job-worker` |
| Eloquent モデルが古い状態 | `SerializesModels` が dispatch 時に ID だけ保存して handle 時に再 fetch | 規約通り **コンストラクタにモデルを渡さない** (プリミティブ / DTO のみ) |
| Job が同じ処理を 2 回実行 | リトライ / 重複 dispatch / 冪等性なし | Job 開始時に「処理済みフラグ」を確認 (冪等設計) |
| 失敗が大量に溜まる | 環境エラーで全部失敗 | `queue:failed` で原因確認 → 修正後 `queue:retry all` |
| メモリリークで OOM | 大量データを 1 Job で処理 | `--memory=256` で 256MB 超で自己終了 → supervisord が再起動 (既に設定済み) |
| Job がいつまでも終わらない | `$timeout` 超過、外部 API hang | `public int $timeout = 60;` で 60 秒で SIGTERM、その後再試行 |
| tinker から `dispatch(closure)` が壊れる | 平文 closure のシリアライズ失敗 | **Job クラスを作って** dispatch する |

---

## 関連

- 規約 (配置 / 命名 / 鉄則): [`../CLAUDE.md §7`](../CLAUDE.md)
- 時刻トリガで定期実行: [`schedule.md`](./schedule.md)
- テスト: [`testing.md`](./testing.md)
- Laravel 公式: <https://laravel.com/docs/queues>
