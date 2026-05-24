// 管理画面 (React) の API クライアント。
// session ベース認証 + CSRF token 付与を共通化する。

import { getCsrfToken } from './types';

export interface AdminApiUser {
    id: number;
    name: string;
    email: string;
    created_at: string;
}

export interface CreateUserResult {
    user: AdminApiUser;
    plain_password: string;
}

export interface ApiError {
    message: string;
    errors?: Record<string, string[]>;
    status: number;
}

/**
 * 共通の fetch ラッパー。
 * - same-origin で cookie を送る (session 認証)
 * - POST / PATCH / DELETE に X-CSRF-TOKEN を自動付与
 * - 4xx / 5xx は ApiError を throw
 */
async function request<T>(input: string, init: RequestInit = {}): Promise<T> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        ...(init.headers as Record<string, string> | undefined),
    };

    const method = (init.method ?? 'GET').toUpperCase();
    if (method !== 'GET' && method !== 'HEAD') {
        headers['X-CSRF-TOKEN'] = getCsrfToken();
        if (init.body !== undefined && !(init.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
        }
    }

    const res = await fetch(input, {
        credentials: 'same-origin',
        ...init,
        headers,
    });

    if (res.status === 204) {
        return undefined as T;
    }

    if (!res.ok) {
        let body: { message?: string; errors?: Record<string, string[]> } = {};
        try {
            body = await res.json();
        } catch {
            // ignore — body 無し JSON も許容
        }
        const err: ApiError = {
            message: body.message ?? `HTTP ${res.status}`,
            errors: body.errors,
            status: res.status,
        };
        throw err;
    }

    return (await res.json()) as T;
}

export const adminApi = {
    listUsers: (): Promise<{ users: AdminApiUser[] }> =>
        request('/admin/api/users'),

    createUser: (input: { name: string; email: string }): Promise<CreateUserResult> =>
        request('/admin/api/users', {
            method: 'POST',
            body: JSON.stringify(input),
        }),

    deleteUser: (id: number): Promise<void> =>
        request(`/admin/api/users/${id}`, { method: 'DELETE' }),
};
