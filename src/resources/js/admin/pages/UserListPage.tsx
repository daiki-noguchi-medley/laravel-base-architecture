// 管理画面: ユーザー一覧 + 削除 (論理削除)

import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPlus, faTrash, faUsers, faGauge } from '@fortawesome/free-solid-svg-icons';
import { adminApi, AdminApiUser } from '../api';

export default function UserListPage() {
    const [userList, setUserList] = useState<AdminApiUser[]>([]);
    const [loading, setLoading] = useState(true);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const loadUserList = async () => {
        setLoading(true);
        setErrorMessage(null);
        try {
            const { users } = await adminApi.listUsers();
            setUserList(users);
        } catch (e: unknown) {
            setErrorMessage((e as { message?: string }).message ?? '一覧取得に失敗しました');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        void loadUserList();
    }, []);

    const handleDelete = async (userId: number, name: string) => {
        if (!window.confirm(`ユーザー「${name}」を削除します。よろしいですか？`)) {
            return;
        }
        try {
            await adminApi.deleteUser(userId);
            setUserList((list) => list.filter((u) => u.id !== userId));
        } catch (e: unknown) {
            setErrorMessage((e as { message?: string }).message ?? '削除に失敗しました');
        }
    };

    return (
        <div className="container mt-4">
            <div className="d-flex justify-content-between align-items-center mb-3">
                <h2 className="mb-0">
                    <FontAwesomeIcon icon={faUsers} className="me-2" />
                    ユーザー一覧
                </h2>
                <div>
                    <Link to="/" className="btn btn-outline-secondary me-2">
                        <FontAwesomeIcon icon={faGauge} className="me-1" />
                        ダッシュボードへ
                    </Link>
                    <Link to="/users/create" className="btn btn-primary">
                        <FontAwesomeIcon icon={faPlus} className="me-1" />
                        新規作成
                    </Link>
                </div>
            </div>

            {errorMessage !== null && (
                <div className="alert alert-danger" role="alert">
                    {errorMessage}
                </div>
            )}

            {loading ? (
                <div className="text-muted">読み込み中...</div>
            ) : (
                <table className="table table-striped table-bordered align-middle">
                    <thead className="table-dark">
                        <tr>
                            <th style={{ width: '6em' }}>ID</th>
                            <th>名前</th>
                            <th>Email</th>
                            <th>作成日時</th>
                            <th style={{ width: '7em' }}>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        {userList.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="text-center text-muted">
                                    ユーザーがいません
                                </td>
                            </tr>
                        ) : (
                            userList.map((user) => (
                                <tr key={user.id}>
                                    <td>{user.id}</td>
                                    <td>{user.name}</td>
                                    <td>{user.email}</td>
                                    <td>{user.created_at}</td>
                                    <td>
                                        <button
                                            type="button"
                                            className="btn btn-sm btn-outline-danger"
                                            onClick={() => void handleDelete(user.id, user.name)}
                                        >
                                            <FontAwesomeIcon icon={faTrash} />
                                        </button>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            )}
        </div>
    );
}
