<?php

declare(strict_types=1);

namespace App\Http\Request\User\Kanban;

use Illuminate\Foundation\Http\FormRequest;

final class CreateCardRequest extends FormRequest
{
    public const string TITLE = 'title';
    public const string BODY  = 'body';

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
            self::TITLE => ['required', 'string', 'max:120'],
            self::BODY  => ['nullable', 'string', 'max:2000'],
        ];
    }
}
