// Vite 設定 — Laravel + Docker 環境向け
//
// Docker 対応の要点:
//   - server.host を 0.0.0.0 にして、コンテナ外 (ホスト) からアクセス可能にする
//   - server.hmr.host = 'localhost' で、ブラウザから HMR サーバーへ接続させる
//   - server.watch.usePolling = true で bind mount 上のファイル変更を検知 (macOS / Windows 必須)
//
// エントリ:
//   - resources/css/app.css           : Laravel 標準 (Tailwind を含む)
//   - resources/js/app.js             : Laravel 標準
//   - resources/js/admin/app.tsx      : 管理画面 (React + Bootstrap + FontAwesome)

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/admin/app.tsx',
            ],
            refresh: true,
        }),
        react(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
        },
        watch: {
            usePolling: true,
            interval: 500,
        },
    },
});
