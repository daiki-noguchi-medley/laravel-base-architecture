<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Kanban;

use App\Enums\KanbanLane;
use App\Http\Requests\User\Kanban\CreateCardRequest;
use App\Http\Requests\User\Kanban\MoveCardRequest;
use App\Http\Requests\User\Kanban\UpdateCardRequest;
use App\Http\Resource\User\Kanban\KanbanCardResource;
use Demo\Service\Kanban\KanbanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Kanban カードの CRUD + Move API (user guard で保護)。
 * Blade からは htmx / fetch で叩かれる。
 */
final class KanbanCardController
{
    public function __construct(
        private readonly KanbanService $kanbanService,
    ) {}

    /**
     * GET /kanban/cards — 自分のカード全件 (lane / position 順)。
     */
    public function index(): JsonResponse
    {
        $userId       = (int) auth('user')->id();
        $cardList     = $this->kanbanService->getBoardByUserId($userId);
        $cardItemList = array_map(
            fn ($card) => (new KanbanCardResource($card))->toArray(),
            $cardList,
        );

        return response()->json(['cards' => $cardItemList]);
    }

    /**
     * POST /kanban/cards — 新規カード作成 (TODO 末尾)。
     */
    public function store(CreateCardRequest $request): JsonResponse
    {
        $userId = (int) auth('user')->id();
        $card   = $this->kanbanService->createCard(
            userId: $userId,
            title: $request->validated(CreateCardRequest::TITLE),
            body: (string) $request->validated(CreateCardRequest::BODY, ''),
        );

        return response()->json(
            (new KanbanCardResource($card))->toArray(),
            Response::HTTP_CREATED,
        );
    }

    /**
     * PATCH /kanban/cards/{id} — title / body の更新。
     */
    public function update(int $id, UpdateCardRequest $request): JsonResponse
    {
        $userId = (int) auth('user')->id();
        $card   = $this->kanbanService->updateCard(
            cardId: $id,
            userId: $userId,
            title: $request->validated(UpdateCardRequest::TITLE),
            body: (string) $request->validated(UpdateCardRequest::BODY, ''),
        );

        return response()->json((new KanbanCardResource($card))->toArray());
    }

    /**
     * PATCH /kanban/cards/{id}/move — lane と position を更新 (DnD)。
     */
    public function move(int $id, MoveCardRequest $request): JsonResponse
    {
        $userId = (int) auth('user')->id();
        $card   = $this->kanbanService->moveCard(
            cardId: $id,
            userId: $userId,
            lane: KanbanLane::from($request->validated(MoveCardRequest::LANE)),
            position: (int) $request->validated(MoveCardRequest::POSITION),
        );

        return response()->json((new KanbanCardResource($card))->toArray());
    }

    /**
     * DELETE /kanban/cards/{id} — 論理削除。
     */
    public function destroy(int $id): Response
    {
        $userId = (int) auth('user')->id();
        $this->kanbanService->deleteCard(cardId: $id, userId: $userId);
        return response()->noContent();
    }
}
