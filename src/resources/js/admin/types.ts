// Blade から JS に <script> で注入される値の型定義 (グローバル)

export interface AdminUser {
    id: number;
    name: string;
    email: string;
}

declare global {
    interface Window {
        // ログイン後ダッシュボードで参照する管理者情報
        __adminUser?: AdminUser;
        // FormRequest のバリデーションエラー (再表示用)
        __pageErrors?: Record<string, string[]>;
        // ログインフォームの old('email') 復元用
        __oldEmail?: string;
    }
}

/**
 * <meta name="csrf-token"> の値を取り出す。
 * Laravel POST フォームの hidden field (_token) として渡す。
 */
export function getCsrfToken(): string {
    const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
    return meta?.content ?? '';
}
