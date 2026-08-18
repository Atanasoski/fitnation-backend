<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Concerns\ConvertsIncomingUnits;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkoutTemplateExerciseRequest extends FormRequest
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
        $this->convertIncomingUnits(['target_weight']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_sets' => 'nullable|integer|min:1',
            'min_target_reps' => 'nullable|integer|min:1',
            'max_target_reps' => 'nullable|integer|min:1|gte:min_target_reps',
            'target_weight' => 'nullable|numeric|min:0',
            'rest_seconds' => 'nullable|integer|min:0',
        ];
    }
}
