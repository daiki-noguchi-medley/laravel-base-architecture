<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\UserManagement;

use App\Http\Requests\Admin\UserManagement\CreateUserRequest;
use App\Http\Resource\Admin\User\CreateUserResultResource;
use App\Http\Resource\Admin\User\UserListItemResource;
use Demo\Service\Admin\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * 管理画面の User CRUD API (admin guard で保護)。
 * SPA (React) から JSON でアクセスされる。
 */
final class UserManagementController
{
    public function __construct(
        private readonly UserManagementService $userManagementService,
    ) {}

    /**
     * GET /admin/api/users — 全ユーザー一覧。
     */
    public function index(): JsonResponse
    {
        $userList     = $this->userManagementService->getUserList();
        $userItemList = array_map(
            fn ($user) => (new UserListItemResource($user))->toArray(),
            $userList,
        );

        return response()->json(['users' => $userItemList]);
    }

    /**
     * POST /admin/api/users — 新規ユーザー作成 (パスワードは自動生成)。
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $result = $this->userManagementService->createUser(
            name: $request->validated(CreateUserRequest::NAME),
            email: $request->validated(CreateUserRequest::EMAIL),
        );

        return response()->json(
            (new CreateUserResultResource($result['user'], $result['plainPassword']))->toArray(),
            Response::HTTP_CREATED,
        );
    }

    /**
     * DELETE /admin/api/users/{id} — 論理削除。
     */
    public function destroy(int $id): Response
    {
        $this->userManagementService->deleteUser($id);
        return response()->noContent();
    }
}
