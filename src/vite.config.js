// Laravel 12 + Vite (laravel-vite-plugin) を Docker 環境で動かすためのテンプレート
//
// 使い方:
//   composer create-project laravel/laravel . でデフォルトの vite.config.js が
//   生成されるので、このファイルで上書きする。
//     docker compose exec app cp /var/www/html/../docker-templates/vite.config.js ./
//   もしくはホスト側で:
//     cp docker/vite.config.js src/vite.config.js
//
// Docker 対応で重要な点:
//   - server.host を 0.0.0.0 にして、コンテナ外 (ホスト) からアクセス可能にする
//   - server.hmr.host = 'localhost' で、ブラウザ (ホスト) から HMR サーバーへ
//     接続できるようにする
//   - server.watch.usePolling = true で、bind mount 上のファイル変更を確実に検知する
//     (特に macOS / Windows の Docker Desktop で必須)

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        // コンテナ内で 0.0.0.0 を bind してホストに公開
        host: '0.0.0.0',
        port: 5173,
        // ブラウザ (ホスト) → Vite Dev サーバーへ接続させる際のホスト名
        hmr: {
            host: 'localhost',
        },
        // bind mount のファイル変更検知を polling に (macOS / Windows で必須)
        watch: {
            usePolling: true,
            interval: 500,
        },
    },
});
