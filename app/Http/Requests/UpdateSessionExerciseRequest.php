<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSessionExerciseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order' => 'nullable|integer|min:0',
            'target_sets' => 'nullable|integer|min:1',
            'min_target_reps' => 'nullable|integer|min:1',
            'max_target_reps' => 'nullable|integer|min:1|gte:min_target_reps',
            'target_weight' => 'nullable|numeric|min:0',
            'rest_seconds' => 'nullable|integer|min:0',
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
            'target_sets.min' => 'Target sets must be at least 1.',
            'min_target_reps.min' => 'Minimum target reps must be at least 1.',
            'max_target_reps.min' => 'Maximum target reps must be at least 1.',
            'max_target_reps.gte' => 'Maximum target reps must be greater than or equal to minimum target reps.',
            'target_weight.min' => 'Target weight cannot be negative.',
            'rest_seconds.min' => 'Rest seconds cannot be negative.',
        ];
    }
}
