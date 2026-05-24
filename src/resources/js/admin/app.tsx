// 管理画面 (React) のエントリポイント
// Vite で resources/js/admin/app.tsx をビルド → public/build/admin に出力
// Blade (admin/app.blade.php) の <div id="admin-app"> にマウントされる

import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import LoginPage from './pages/LoginPage';
import DashboardPage from './pages/DashboardPage';
import UserListPage from './pages/UserListPage';
import UserCreatePage from './pages/UserCreatePage';
import 'bootstrap/dist/css/bootstrap.min.css';
import './app.css';

const container = document.getElementById('admin-app');
if (container) {
    createRoot(container).render(
        <StrictMode>
            <BrowserRouter basename="/admin">
                <Routes>
                    <Route path="/login" element={<LoginPage />} />
                    <Route path="/" element={<DashboardPage />} />
                    <Route path="/users" element={<UserListPage />} />
                    <Route path="/users/create" element={<UserCreatePage />} />
                    {/* それ以外は dashboard へ (router で吸収) */}
                    <Route path="*" element={<Navigate to="/" replace />} />
                </Routes>
            </BrowserRouter>
        </StrictMode>,
    );
}
