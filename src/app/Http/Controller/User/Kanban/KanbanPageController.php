<?php

declare(strict_types=1);

namespace App\Http\Controller\User\Kanban;

use App\Http\ViewModel\User\Kanban\KanbanPageViewModel;
use Illuminate\Contracts\View\View;

/**
 * GET /kanban — Kanban ボード Blade の素のページ。
 * 中身は Alpine.js + SortableJS で fetch しに行く SPA-like 構造。
 */
final class KanbanPageController
{
    public function index(): View
    {
        return view('user.kanban.index', [
            'vm' => KanbanPageViewModel::build(),
        ]);
    }
}
