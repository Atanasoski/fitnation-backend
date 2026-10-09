<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class DeleteUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The password is optional (decided 2026-10-02): the mobile app confirms
     * deletion with a typed word, the bearer token already says who is asking,
     * and a social account has no password to type. A password that is sent —
     * the web app still asks for one — must be the user's current one.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'password' => ['nullable', 'string', 'current_password'],
        ];
    }
}
