<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConvertsIncomingUnits;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSetRequest extends FormRequest
{
    use ConvertsIncomingUnits;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Convert an incoming weight to the canonical kg storage unit before
     * validation runs, based on the authenticated user's stored preference.
     */
    protected function prepareForValidation(): void
    {
        $this->convertIncomingUnits(['weight']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'weight' => 'required|numeric|min:0',
            'reps' => 'required|integer|min:0',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'weight.required' => 'The weight is required.',
            'weight.min' => 'The weight cannot be negative.',
            'reps.required' => 'The number of reps is required.',
            'reps.min' => 'The number of reps cannot be negative.',
        ];
    }
}
