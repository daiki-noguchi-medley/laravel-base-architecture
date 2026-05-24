// User 画面 Kanban ボード (htmx + Alpine.js + SortableJS)
//
// 構成:
//   - Alpine.js でカード state + モーダル制御
//   - SortableJS で DnD (4 レーン間も移動可、共通 group 'kanban')
//   - サーバとは fetch + CSRF token で同期
//
// 注意:
//   - card の id / title / body は Alpine の state に格納し、DOM 操作で書き換えない
//     (Alpine の reactivity に任せる)
//   - SortableJS の onEnd で「移動先 lane + position」を計算してサーバへ送る

import Alpine from 'alpinejs';
import Sortable from 'sortablejs';

const LANES = ['todo', 'doing', 'review', 'done'];

// <meta name="csrf-token"> から token を取り出す。
function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.content ?? '';
}

// 共通 fetch ラッパー (session + CSRF 必須)。
async function api(method, url, body) {
    const headers = { Accept: 'application/json' };
    if (method !== 'GET') {
        headers['X-CSRF-TOKEN'] = csrfToken();
        if (body !== undefined) headers['Content-Type'] = 'application/json';
    }
    const res = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers,
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });
    if (res.status === 204) return null;
    if (!res.ok) {
        const errBody = await res.json().catch(() => ({}));
        throw new Error(errBody.message ?? `HTTP ${res.status}`);
    }
    return res.json();
}

// Alpine の Kanban store。
// グローバルに 1 インスタンス。Blade 側から x-data="kanban" でアクセス。
Alpine.data('kanban', () => ({
    laneList: LANES,
    cardList: [],
    loading: true,
    errorMessage: null,
    // 編集モーダル state
    modalOpen: false,
    modalMode: 'create', // 'create' | 'edit'
    modalCardId: null,
    modalTitle: '',
    modalBody: '',

    /**
     * ボード初期化: サーバからカードを GET し、SortableJS を全レーンに装着する。
     */
    async init() {
        try {
            const { cards } = await api('GET', '/kanban/cards');
            this.cardList = cards;
        } catch (e) {
            this.errorMessage = e.message;
        } finally {
            this.loading = false;
        }
        // DOM 更新後に SortableJS を装着 ($nextTick で Alpine 側のレンダリング待ち)
        this.$nextTick(() => this.attachSortables());
    },

    /**
     * 各レーンの <div data-lane="..."> に SortableJS を装着。
     * group 名を 'kanban' で共通化することでレーン間移動を可能にする。
     */
    attachSortables() {
        for (const lane of this.laneList) {
            const el = this.$root.querySelector(`[data-lane="${lane}"]`);
            if (el === null) continue;
            Sortable.create(el, {
                group: 'kanban',
                animation: 150,
                handle: '.kanban-card',
                ghostClass: 'kanban-card-ghost',
                onEnd: (ev) => void this.onSortEnd(ev),
            });
        }
    },

    /**
     * DnD 完了時: 移動先の lane と新しい position を計算して PATCH。
     */
    async onSortEnd(ev) {
        const cardId = Number(ev.item.dataset.cardId);
        const newLane = ev.to.dataset.lane;
        const newPosition = ev.newIndex;
        try {
            const updated = await api('PATCH', `/kanban/cards/${cardId}/move`, {
                lane: newLane,
                position: newPosition,
            });
            // local state を更新 (lane と position を反映)
            const idx = this.cardList.findIndex((c) => c.id === cardId);
            if (idx >= 0) this.cardList[idx] = updated;
        } catch (e) {
            this.errorMessage = e.message;
            // 失敗時は DOM 状態とサーバ状態がズレるので再ロード
            await this.reload();
        }
    },

    async reload() {
        const { cards } = await api('GET', '/kanban/cards');
        this.cardList = cards;
    },

    /**
     * 指定 lane に属するカードを position 昇順で返す (Alpine x-for 用)。
     */
    cardsOfLane(lane) {
        return this.cardList
            .filter((c) => c.lane === lane)
            .sort((a, b) => a.position - b.position);
    },

    // ─── モーダル制御 ───────────────────────────────
    openCreate() {
        this.modalMode = 'create';
        this.modalCardId = null;
        this.modalTitle = '';
        this.modalBody = '';
        this.modalOpen = true;
    },

    openEdit(card) {
        this.modalMode = 'edit';
        this.modalCardId = card.id;
        this.modalTitle = card.title;
        this.modalBody = card.body;
        this.modalOpen = true;
    },

    closeModal() {
        this.modalOpen = false;
    },

    async submitModal() {
        if (this.modalTitle.trim() === '') {
            this.errorMessage = 'タイトルは必須です';
            return;
        }
        try {
            if (this.modalMode === 'create') {
                const card = await api('POST', '/kanban/cards', {
                    title: this.modalTitle,
                    body: this.modalBody,
                });
                this.cardList.push(card);
            } else {
                const updated = await api('PATCH', `/kanban/cards/${this.modalCardId}`, {
                    title: this.modalTitle,
                    body: this.modalBody,
                });
                const idx = this.cardList.findIndex((c) => c.id === updated.id);
                if (idx >= 0) this.cardList[idx] = updated;
            }
            this.closeModal();
        } catch (e) {
            this.errorMessage = e.message;
        }
    },

    async deleteCard(cardId) {
        if (!window.confirm('このカードを削除しますか？')) return;
        try {
            await api('DELETE', `/kanban/cards/${cardId}`);
            this.cardList = this.cardList.filter((c) => c.id !== cardId);
        } catch (e) {
            this.errorMessage = e.message;
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();
