// 管理画面: ユーザー新規作成
// 作成成功時は自動生成された平文パスワードを 1 回だけ表示する。

import { FormEvent, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faArrowLeft, faCheck, faCopy, faKey, faTriangleExclamation, faUserPlus,
} from '@fortawesome/free-solid-svg-icons';
import { adminApi, CreateUserResult } from '../api';

export default function UserCreatePage() {
    const navigate = useNavigate();
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
    const [resultMessage, setResultMessage] = useState<string | null>(null);
    const [createdUser, setCreatedUser] = useState<CreateUserResult | null>(null);
    const [copied, setCopied] = useState(false);

    const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        setSubmitting(true);
        setFieldErrors({});
        setResultMessage(null);
        try {
            const result = await adminApi.createUser({ name, email });
            setCreatedUser(result);
            setName('');
            setEmail('');
        } catch (err: unknown) {
            const e = err as { status?: number; message?: string; errors?: Record<string, string[]> };
            if (e.status === 422 && e.errors !== undefined) {
                setFieldErrors(e.errors);
            } else {
                setResultMessage(e.message ?? '作成に失敗しました');
            }
        } finally {
            setSubmitting(false);
        }
    };

    const handleCopyPassword = async () => {
        if (createdUser === null) return;
        try {
            await navigator.clipboard.writeText(createdUser.plain_password);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            // 一部ブラウザは https 必須なので失敗しても致命的ではない
            setResultMessage('クリップボードコピーに失敗しました (手動で控えてください)');
        }
    };

    if (createdUser !== null) {
        return (
            <div className="container mt-4" style={{ maxWidth: 600 }}>
                <div className="card">
                    <div className="card-header bg-success text-white">
                        <FontAwesomeIcon icon={faCheck} className="me-2" />
                        作成しました
                    </div>
                    <div className="card-body">
                        <p>ID: <strong>{createdUser.user.id}</strong></p>
                        <p>名前: <strong>{createdUser.user.name}</strong></p>
                        <p>Email: <strong>{createdUser.user.email}</strong></p>

                        <div className="alert alert-warning mt-3 mb-3">
                            <FontAwesomeIcon icon={faTriangleExclamation} className="me-2" />
                            <strong>初期パスワードはこの画面でしか表示されません。</strong>
                            ユーザー本人に伝えたら、この画面を閉じてください。
                        </div>

                        <label className="form-label">
                            <FontAwesomeIcon icon={faKey} className="me-2" />
                            初期パスワード
                        </label>
                        <div className="input-group mb-3">
                            <input
                                type="text"
                                readOnly
                                value={createdUser.plain_password}
                                className="form-control font-monospace"
                            />
                            <button
                                type="button"
                                className="btn btn-outline-primary"
                                onClick={() => void handleCopyPassword()}
                            >
                                <FontAwesomeIcon icon={copied ? faCheck : faCopy} className="me-1" />
                                {copied ? 'コピー済み' : 'コピー'}
                            </button>
                        </div>

                        {resultMessage !== null && (
                            <div className="alert alert-danger">{resultMessage}</div>
                        )}

                        <button
                            type="button"
                            className="btn btn-primary"
                            onClick={() => navigate('/users')}
                        >
                            一覧へ戻る
                        </button>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="container mt-4" style={{ maxWidth: 600 }}>
            <h2>
                <FontAwesomeIcon icon={faUserPlus} className="me-2" />
                ユーザー新規作成
            </h2>
            <p className="text-muted">
                初期パスワードは自動生成され、作成後の画面に 1 回だけ表示されます。
            </p>

            {resultMessage !== null && (
                <div className="alert alert-danger">{resultMessage}</div>
            )}

            <form onSubmit={(e) => void handleSubmit(e)}>
                <div className="mb-3">
                    <label htmlFor="name" className="form-label">名前</label>
                    <input
                        id="name"
                        type="text"
                        className={`form-control ${fieldErrors.name !== undefined ? 'is-invalid' : ''}`}
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        required
                        autoFocus
                    />
                    {fieldErrors.name !== undefined && (
                        <div className="invalid-feedback">{fieldErrors.name[0]}</div>
                    )}
                </div>

                <div className="mb-3">
                    <label htmlFor="email" className="form-label">Email</label>
                    <input
                        id="email"
                        type="email"
                        className={`form-control ${fieldErrors.email !== undefined ? 'is-invalid' : ''}`}
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        required
                    />
                    {fieldErrors.email !== undefined && (
                        <div className="invalid-feedback">{fieldErrors.email[0]}</div>
                    )}
                </div>

                <div className="d-flex justify-content-between">
                    <Link to="/users" className="btn btn-outline-secondary">
                        <FontAwesomeIcon icon={faArrowLeft} className="me-1" />
                        戻る
                    </Link>
                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={submitting}
                    >
                        {submitting ? '作成中...' : '作成する'}
                    </button>
                </div>
            </form>
        </div>
    );
}
