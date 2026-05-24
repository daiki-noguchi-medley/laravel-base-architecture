// User 画面用バンドル (htmx + Alpine.js)
//
// CDN 依存 (unpkg.com) を廃止してローカル (Vite ビルド) に内包する。
// CDN は外部障害 (unpkg downtime / ネット切断 / CSP) でページが動かなくなるため。
//
// layouts/user.blade.php から `@vite(['resources/js/user.js'])` で読み込まれる。

import 'htmx.org';
import Alpine from 'alpinejs';

// Alpine は明示的に start() が必要
window.Alpine = Alpine;
Alpine.start();
