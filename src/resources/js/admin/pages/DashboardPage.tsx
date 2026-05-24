import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faGauge, faRightFromBracket, faUser, faUsers } from '@fortawesome/free-solid-svg-icons';
import { Link } from 'react-router-dom';
import { getCsrfToken } from '../types';

export default function DashboardPage() {
    const admin = window.__adminUser;

    if (admin === undefined) {
        // 未認証で /admin に来た場合 (本来は Laravel middleware で /admin/login にリダイレクト済み)
        return (
            <div className="container mt-5">
                <div className="alert alert-warning">
                    認証情報を取得できませんでした。
                    <a href="/admin/login" className="alert-link ms-2">ログインへ</a>
                </div>
            </div>
        );
    }

    return (
        <>
            <nav className="navbar navbar-dark bg-dark">
                <div className="container-fluid">
                    <span className="navbar-brand mb-0 h1">
                        <FontAwesomeIcon icon={faGauge} className="me-2" />
                        管理画面
                    </span>
                    <form method="POST" action="/admin/logout" className="d-flex m-0">
                        <input type="hidden" name="_token" value={getCsrfToken()} />
                        <button type="submit" className="btn btn-outline-light">
                            <FontAwesomeIcon icon={faRightFromBracket} className="me-2" />
                            ログアウト
                        </button>
                    </form>
                </div>
            </nav>

            <div className="container mt-4">
                <div className="card">
                    <div className="card-header">
                        <FontAwesomeIcon icon={faUser} className="me-2" />
                        ログイン中の管理者
                    </div>
                    <div className="card-body">
                        <p className="mb-1"><strong>ID:</strong> {admin.id}</p>
                        <p className="mb-1"><strong>名前:</strong> {admin.name}</p>
                        <p className="mb-0"><strong>Email:</strong> {admin.email}</p>
                    </div>
                </div>

                <div className="card mt-4">
                    <div className="card-header">機能メニュー</div>
                    <div className="card-body">
                        <Link to="/users" className="btn btn-primary me-2">
                            <FontAwesomeIcon icon={faUsers} className="me-2" />
                            ユーザー管理
                        </Link>
                    </div>
                </div>

                <div className="card mt-4">
                    <div className="card-header">React + Bootstrap + FontAwesome デモ</div>
                    <div className="card-body">
                        <p>このカードは React コンポーネントで描画されています。</p>
                        <button className="btn btn-primary me-2">
                            <FontAwesomeIcon icon={faGauge} className="me-2" />
                            プライマリ
                        </button>
                        <button className="btn btn-success">
                            <FontAwesomeIcon icon={faUser} className="me-2" />
                            サクセス
                        </button>
                    </div>
                </div>
            </div>
        </>
    );
}
