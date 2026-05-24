# タスクスケジュール (Schedule)

Laravel の Task Scheduling 機能の使い方。配置・命名規約は [`../CLAUDE.md §7`](../CLAUDE.md) を参照。
このドキュメントは **how-to (使い方)** にフォーカス。

---

## 概要

cron 的な **時刻トリガ** で artisan command / Job / closure を定期実行する仕組み。
スケジュール定義は **`routes/console.php`** に PHP コードで書く (crontab を手書きしない)。

```
[batch コンテナ]
   ├─ [supervisord]
   │     ├─ [cron daemon]  ← /etc/cron.d/laravel-scheduler を毎分発火
   │     │       ↓
   │     │   php artisan schedule:run
   │     │       ↓
   │     │   routes/console.php を読んで「今分に該当するスケジュール」を実行
   │     │       ├─ Schedule::command(...)  → 直接 artisan を fork & run
   │     │       ├─ Schedule::job(...)      → jobs テーブルに dispatch (job コンテナが処理)
   │     │       └─ Schedule::call(closure) → batch コンテナ内で直接実行
   │     │
   │     └─ [cron-tail]   ← /var/log/scheduler-cron.log を docker logs に流す
```

> ⚠️ scheduler 自体は **Job を実行しない** (時刻トリガで dispatch するだけ)。
> 重い処理は `Schedule::job(...)` 経由で job コンテナの queue へ投げるのが基本。

---

## このプロジェクトの構成

| 項目 | 値 |
|---|---|
| 実装方式 | **cron daemon** (`/usr/sbin/cron -f`) ※ Laravel 公式 Production パターン |
| コンテナ | `batch` |
| supervisord 設定 | `docker/php/supervisord-batch.conf` |
| crontab | `docker/cron/laravel-scheduler` (bind mount で `/etc/cron.d/` へ) |
| 実体 | `* * * * * www-data php /var/www/html/artisan schedule:run --verbose --no-interaction >> storage/logs/scheduler-cron.log 2>&1` |
| スケジュール定義 | `src/routes/console.php` |

**`schedule:work` (Laravel 11+ 限定) もシェルループも採らない**。
理由: cron は Linux 標準で運用ツール / モニタリングが揃っており、Laravel 10/11/12/13 共通。

---

## スケジュールを登録する

`src/routes/console.php` に `Schedule::` で登録する。

```php
<?php

declare(strict_types=1);

use App\Jobs\CleanupInactiveUserJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

// (1) artisan command を定期実行
Schedule::command('user:import-csv /var/data/users.csv')->dailyAt('03:00');

// (2) Job を queue に dispatch (重い処理はこれ、job コンテナが処理)
Schedule::job(new CleanupInactiveUserJob())->hourly();

// (3) closure を直接実行 (軽い処理のみ、batch コンテナ内で実行)
Schedule::call(function () {
    Log::info('[heartbeat] alive at ' . now()->format('H:i:s'));
})->everyFiveMinutes();
```

---

## 頻度の指定方法

| メソッド | 意味 |
|---|---|
| `->everyMinute()` | 毎分 |
| `->everyTwoMinutes()` / `->everyFiveMinutes()` / `->everyTenMinutes()` | N 分ごと |
| `->hourly()` | 毎時 0 分 |
| `->hourlyAt(15)` | 毎時 15 分 |
| `->daily()` | 毎日 00:00 |
| `->dailyAt('13:00')` | 毎日 13:00 |
| `->twiceDaily(1, 13)` | 1:00 と 13:00 |
| `->weekly()` | 毎週日曜 00:00 |
| `->weeklyOn(1, '8:00')` | 毎週月曜 08:00 (0=日, 1=月, ..., 6=土) |
| `->monthly()` / `->monthlyOn(15, '14:00')` | 毎月 / 毎月 15 日 14:00 |
| `->yearly()` | 毎年 1/1 00:00 |
| `->cron('*/15 9-17 * * 1-5')` | crontab 構文を直接 |

### 制限・修飾子

```php
Schedule::command('reports:daily')
    ->dailyAt('03:00')
    ->timezone('Asia/Tokyo')                    // タイムゾーン (default は config/app.php の timezone)
    ->weekdays()                                 // 平日のみ (or weekends())
    ->between('1:00', '5:00')                    // 時間帯指定
    ->unlessBetween('23:00', '6:00')             // 除外時間帯
    ->when(fn () => app()->environment('production'))   // 条件
    ->skip(fn () => Cache::has('maintenance'))   // skip 条件
    ->withoutOverlapping()                       // 前回の実行が終わっていなければスキップ
    ->onOneServer()                              // 複数サーバー環境で 1 台だけ実行
    ->runInBackground();                         // 非ブロッキング
```

### よく使う組み合わせ

| 用途 | 書き方 |
|---|---|
| 平日 09:00 に slack 通知 | `->dailyAt('09:00')->weekdays()` |
| 1 時間ごと、ただし夜間スキップ | `->hourly()->between('7:00', '23:00')` |
| 重複防止 (前回未完了ならスキップ) | `->withoutOverlapping(10)` ※ 10 = lock 寿命分 |
| 毎日 03:00、長時間ジョブ | `->dailyAt('03:00')->onOneServer()->withoutOverlapping()->runInBackground()` |

---

## 3 つの登録方法の違い

| 方法 | 実行場所 | 用途 |
|---|---|---|
| **`Schedule::command(...)`** | batch コンテナ内で `php artisan ...` を fork & run | artisan コマンド (短時間で終わるもの) |
| **`Schedule::job(...)`** | jobs テーブルに dispatch → **job コンテナ** が処理 | Job、特に重い処理・長時間処理 |
| **`Schedule::call(closure)`** | batch コンテナ内で直接実行 | 軽い処理 (heartbeat、Cache 更新 等) |

### 重い処理は必ず `Schedule::job(...)`

```php
// NG (batch コンテナで重い処理 → cron が次の分に間に合わない)
Schedule::call(fn () => DB::table('huge_table')->update(...))->hourly();

// OK (重い処理は queue 経由で job コンテナへ)
Schedule::job(new HeavyAggregationJob())->hourly();
```

batch コンテナの `schedule:run` は 1 分以内に終わらないと **次の発火に間に合わない**。
重い処理は dispatch で逃がす。

---

## 実行確認

### 登録されているスケジュール一覧

```bash
docker compose exec app php artisan schedule:list
```

```
0  *  *  *  *  hourly  ......................... App\Jobs\CleanupInactiveUserJob
0  3  *  *  *  daily   ......................... user:import-csv /var/data/users.csv
*/5 * *  *  *  every 5 min .................... Closure
```

### 特定のスケジュールを今すぐ実行 (Laravel 11+)

```bash
docker compose exec app php artisan schedule:test
# → リスト表示 + 番号で選択 → そのスケジュールが即時実行される
```

### schedule:run を手動 1 回実行 (デバッグ)

```bash
docker compose exec app php artisan schedule:run --verbose --no-interaction
```

「今分に発火するもの」を実行 (= cron daemon が毎分やっているのと同じ)。

---

## 実行ログの確認

### scheduler-cron.log

`schedule:run` の標準出力は `storage/logs/scheduler-cron.log` に書き出される (crontab 設定):

```bash
docker compose exec app tail -f storage/logs/scheduler-cron.log
```

例:
```
Running [Callback] ......................... 7.68ms DONE
No scheduled commands are ready to run.
```

### docker compose logs scheduler (cron-tail program 経由)

supervisor の `cron-tail` program が `tail -F scheduler-cron.log` を stdout に流す。
そのため:

```bash
docker compose logs batch -f
```

でも同じ内容が見える。

### Laravel ログ (Schedule 内の Log::info)

`Schedule::call()` 内で `Log::info(...)` を呼んだ場合は `storage/logs/laravel.log`:

```bash
docker compose exec app tail -f storage/logs/laravel.log
```

---

## なぜ `schedule:work` でも `while true` ループでもなく cron daemon か

| 方式 | Laravel 公式 | 採用するか | 理由 |
|---|---|---|---|
| **cron daemon** + `schedule:run` を毎分 | ◎ Production 推奨 | ✓ 採用 | Linux 標準、運用ツール / モニタリングが揃ってる、10〜13 共通 |
| `schedule:work` | 〇 11+ で導入された開発用 | ✗ | Laravel 11+ 限定で 10 互換なし |
| `while true; sleep 60; schedule:run; done` | △ 簡易 | ✗ | cron と挙動同じだが運用慣習から外れる |

---

## ハマりどころ

| 症状 | 原因 | 対処 |
|---|---|---|
| schedule が発火しない | cron daemon が止まっている | `docker compose exec batch supervisorctl status` で `cron RUNNING` 確認 |
| schedule:run の出力が `docker compose logs batch` に出ない | crontab が `/proc/1/fd/1` に書き込み失敗 (www-data から書けない) | 出力先を `storage/logs/scheduler-cron.log` にして `tail -F` で stdout に流す (現状すでに対応) |
| タイムゾーンがずれて時刻が UTC | `config/app.php` の `timezone` が UTC ハードコード | `env('APP_TIMEZONE', 'UTC')` に修正 + `.env` で `APP_TIMEZONE=Asia/Tokyo` (現状すでに対応) |
| 前の実行が長くて重複起動する | `withoutOverlapping()` をつけていない | `->withoutOverlapping(10)` を追加 |
| 複数サーバーで全部が同じ処理を実行 | スケーリング時 | `->onOneServer()` を追加 (cache.lock が必要) |
| `Schedule::call` が時間かかる | batch コンテナが詰まる | `Schedule::job(new XxxJob)` に書き換えて job コンテナに逃がす |
| crontab を書き換えても反映されない | bind mount じゃない or キャッシュ | `docker compose restart batch` |

---

## 関連

- 規約 (配置 / 命名 / 鉄則): [`../CLAUDE.md §7`](../CLAUDE.md)
- 非同期 Job: [`queue.md`](./queue.md)
- GitHub Actions / インフラ: [`infra/github-actions.md`](./infra/github-actions.md)
- Laravel 公式: <https://laravel.com/docs/scheduling>
