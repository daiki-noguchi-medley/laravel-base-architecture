<?php

declare(strict_types=1);

namespace App\Http\Resource\Admin\User;

use App\Model\User\User;
use Illuminate\Contracts\Support\Arrayable;

/**
 * 管理画面の user 一覧 1 行ぶん。
 *
 * @implements Arrayable<string, int|string>
 */
final readonly class UserListItemResource implements Arrayable
{
    public function __construct(
        private User $user,
    ) {}

    /**
     * @return array{id: int, name: string, email: string, created_at: string}
     */
    public function toArray(): array
    {
        return [
            'id'         => $this->user->id,
            'name'       => $this->user->name,
            'email'      => $this->user->email,
            'created_at' => $this->user->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
