<?php

namespace App\Http\Requests;

use App\Enums\UnitSystem;
use App\Services\UnitConversionService;
use Illuminate\Foundation\Http\FormRequest;

class LogSetRequest extends FormRequest
{
    public function __construct(
        private readonly UnitConversionService $conversionService,
    ) {
        parent::__construct();
    }

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
        $unitSystem = $this->user()?->profile?->unit_system ?? UnitSystem::Metric;

        if ($unitSystem !== UnitSystem::Imperial || ! $this->filled('weight')) {
            return;
        }

        $this->merge([
            'weight' => $this->conversionService->toKg((float) $this->input('weight'), UnitSystem::Imperial),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'exercise_id' => 'required|exists:workout_exercises,id',
            'set_number' => 'required|integer|min:1',
            'weight' => 'required|numeric|min:0',
            'reps' => 'required|integer|min:0',
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
            'exercise_id.required' => 'An exercise must be selected.',
            'exercise_id.exists' => 'The selected exercise does not exist.',
            'set_number.required' => 'The set number is required.',
            'set_number.min' => 'The set number must be at least 1.',
            'weight.required' => 'The weight is required.',
            'weight.min' => 'The weight cannot be negative.',
            'reps.required' => 'The number of reps is required.',
            'reps.min' => 'The number of reps cannot be negative.',
        ];
    }
}
