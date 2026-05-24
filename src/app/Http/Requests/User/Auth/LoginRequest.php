<?php

declare(strict_types=1);

namespace App\Http\Requests\User\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    public const string EMAIL    = 'email';
    public const string PASSWORD = 'password';
    public const string REMEMBER = 'remember';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            self::EMAIL    => ['required', 'string', 'email'],
            self::PASSWORD => ['required', 'string'],
            self::REMEMBER => ['nullable', 'boolean'],
        ];
    }
}
