<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerExerciseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->hasRole('partner_admin') && $user->partner !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::overrideRules();
    }

    /**
     * What a Partner Override write accepts. The super admin's override route
     * validates with the same rules.
     *
     * @return array<string, array<int, string>>
     */
    public static function overrideRules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'video' => ['nullable', 'mimes:mp4,webm,ogg', 'max:51200'],
            'remove_video' => ['nullable', 'boolean'],
        ];
    }
}
