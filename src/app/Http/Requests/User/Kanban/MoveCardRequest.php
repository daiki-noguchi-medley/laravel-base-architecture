<?php

declare(strict_types=1);

namespace App\Http\Requests\User\Kanban;

use App\Enums\KanbanLane;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MoveCardRequest extends FormRequest
{
    public const string LANE     = 'lane';
    public const string POSITION = 'position';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rules\Enum>|string>
     */
    public function rules(): array
    {
        return [
            self::LANE     => ['required', Rule::enum(KanbanLane::class)],
            self::POSITION => ['required', 'integer', 'min:0'],
        ];
    }
}
