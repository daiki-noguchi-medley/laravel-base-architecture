<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// =============================================================================
// API (将来の JSON / 外部公開 API 用)
//   - bootstrap/app.php の `withRouting(api: ...)` で読み込まれる
//   - 自動的に `/api` prefix + `api` middleware group (throttle:api 等) が適用される
//   - stateless 前提 (session を使わない)。
//     session 認証が必要な user 向け htmx エンドポイントは routes/web.php に置く
//   - 将来ここに JSON API (mobile / external) を追加する
// =============================================================================

Route::get('/health', fn () => response()->json(['status' => 'ok']));
