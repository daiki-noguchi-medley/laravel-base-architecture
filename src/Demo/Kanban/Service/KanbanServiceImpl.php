<?php

declare(strict_types=1);

namespace Demo\Kanban\Service;

use App\Enum\Kanban\KanbanLane;
use Demo\Kanban\Repository\KanbanCardRepository;
use App\Model\Kanban\KanbanCard;
use InvalidArgumentException;

final class KanbanServiceImpl implements KanbanService
{
    public function __construct(
        private readonly KanbanCardRepository $kanbanCardRepository,
    ) {}

    public function getBoardByUserId(int $userId): array
    {
        return $this->kanbanCardRepository->getCardListByUserId($userId);
    }

    public function createCard(int $userId, string $title, string $body): KanbanCard
    {
        // 新規カードは TODO レーンの末尾に積む。
        $position = $this->kanbanCardRepository->maxPositionOfLane($userId, KanbanLane::TODO) + 1;

        $cardId = $this->kanbanCardRepository->insert(
            userId: $userId,
            title: $title,
            body: $body,
            lane: KanbanLane::TODO,
            position: $position,
        );

        return $this->kanbanCardRepository->findById($cardId)
            ?? throw new \RuntimeException("created card not found: {$cardId}");
    }

    public function updateCard(int $cardId, int $userId, string $title, string $body): KanbanCard
    {
        $this->mustGetOwnedCard($cardId, $userId);
        $this->kanbanCardRepository->updateContent($cardId, $title, $body);

        return $this->kanbanCardRepository->findById($cardId)
            ?? throw new \RuntimeException("updated card not found: {$cardId}");
    }

    public function moveCard(int $cardId, int $userId, KanbanLane $lane, int $position): KanbanCard
    {
        $this->mustGetOwnedCard($cardId, $userId);
        $this->kanbanCardRepository->updatePosition($cardId, $lane, $position);

        return $this->kanbanCardRepository->findById($cardId)
            ?? throw new \RuntimeException("moved card not found: {$cardId}");
    }

    public function deleteCard(int $cardId, int $userId): void
    {
        $this->mustGetOwnedCard($cardId, $userId);
        $this->kanbanCardRepository->softDelete($cardId);
    }

    /**
     * カードを取得しつつ、存在チェックと所有者チェックを同時に行う。
     * IDOR (他人のカードを ID 直叩きで操作する攻撃) を防ぐ guard clause。
     *
     * @param int $cardId 対象カード ID
     * @param int $userId 認可ユーザー ID
     * @return KanbanCard 所有者一致のカード
     * @throws InvalidArgumentException カードが存在しない / 所有者が違う
     */
    private function mustGetOwnedCard(int $cardId, int $userId): KanbanCard
    {
        $card = $this->kanbanCardRepository->findById($cardId)
            ?? throw new InvalidArgumentException("card not found: {$cardId}");

        if ($card->userId !== $userId) {
            throw new InvalidArgumentException("card not owned by user: card={$cardId}, user={$userId}");
        }
        return $card;
    }
}
