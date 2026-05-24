import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faRightToBracket } from '@fortawesome/free-solid-svg-icons';
import { getCsrfToken } from '../types';

export default function LoginPage() {
    const errors = window.__pageErrors ?? {};
    const oldEmail = window.__oldEmail ?? '';

    return (
        <div className="container" style={{ maxWidth: '420px', marginTop: '4rem' }}>
            <div className="card shadow-sm">
                <div className="card-body p-4">
                    <h1 className="h4 mb-4 text-center">
                        <FontAwesomeIcon icon={faRightToBracket} className="me-2" />
                        管理者ログイン
                    </h1>

                    {/* Laravel への form submit (CSRF token は hidden _token として送る) */}
                    <form method="POST" action="/admin/login">
                        <input type="hidden" name="_token" value={getCsrfToken()} />

                        <div className="mb-3">
                            <label htmlFor="email" className="form-label">メールアドレス</label>
                            <input
                                type="email"
                                name="email"
                                id="email"
                                className={`form-control ${errors.email ? 'is-invalid' : ''}`}
                                defaultValue={oldEmail}
                                required
                                autoFocus
                                autoComplete="email"
                            />
                            {errors.email?.[0] && (
                                <div className="invalid-feedback">{errors.email[0]}</div>
                            )}
                        </div>

                        <div className="mb-3">
                            <label htmlFor="password" className="form-label">パスワード</label>
                            <input
                                type="password"
                                name="password"
                                id="password"
                                className="form-control"
                                required
                                autoComplete="current-password"
                            />
                        </div>

                        <div className="form-check mb-3">
                            <input
                                type="checkbox"
                                name="remember"
                                id="remember"
                                value="1"
                                className="form-check-input"
                            />
                            <label htmlFor="remember" className="form-check-label">
                                ログイン状態を保持
                            </label>
                        </div>

                        <button type="submit" className="btn btn-primary w-100">
                            <FontAwesomeIcon icon={faRightToBracket} className="me-2" />
                            ログイン
                        </button>
                    </form>
                </div>
            </div>
        </div>
    );
}
