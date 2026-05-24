<?php

declare(strict_types=1);

namespace App\Http\Resource\Admin\User;

use Demo\Repository\User\UserRow;
use Illuminate\Contracts\Support\Arrayable;

/**
 * ユーザー作成 API のレスポンス。
 * `plain_password` は **作成直後 1 回だけ** 画面に表示するためのもので、
 * 以後 DB には平文を保存しない (hash 済みのみ保存)。
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class CreateUserResultResource implements Arrayable
{
    public function __construct(
        private UserRow $user,
        private string $plainPassword,
    ) {}

    /**
     * @return array{
     *     user: array{id: int, name: string, email: string, created_at: string},
     *     plain_password: string
     * }
     */
    public function toArray(): array
    {
        return [
            'user' => [
                'id'         => $this->user->id,
                'name'       => $this->user->name,
                'email'      => $this->user->email,
                'created_at' => $this->user->createdAt->format('Y-m-d H:i:s'),
            ],
            'plain_password' => $this->plainPassword,
        ];
    }
}
