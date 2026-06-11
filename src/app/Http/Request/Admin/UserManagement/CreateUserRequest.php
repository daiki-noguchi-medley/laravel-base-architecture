<?php

declare(strict_types=1);

namespace App\Http\Request\Admin\UserManagement;

use Illuminate\Foundation\Http\FormRequest;

final class CreateUserRequest extends FormRequest
{
    public const string NAME  = 'name';
    public const string EMAIL = 'email';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            self::NAME  => ['required', 'string', 'max:255'],
            // unique:user は論理削除済みのレコードも含めて重複扱いする。
            // 削除済みユーザーと同じ Email を再利用したい場合は WHERE deleted_at IS NULL の
            // Rule::unique を使うが、今は重複扱いで運用する (シンプル優先)。
            self::EMAIL => ['required', 'email:strict', 'max:255', 'unique:user,email'],
        ];
    }
}
