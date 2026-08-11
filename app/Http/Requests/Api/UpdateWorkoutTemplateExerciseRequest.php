<?php

namespace App\Http\Requests\Api;

use App\Enums\UnitSystem;
use App\Services\UnitConversionService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkoutTemplateExerciseRequest extends FormRequest
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

        if ($unitSystem !== UnitSystem::Imperial || ! $this->filled('target_weight')) {
            return;
        }

        $this->merge([
            'target_weight' => $this->conversionService->toKg((float) $this->input('target_weight'), UnitSystem::Imperial),
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
            'target_sets' => 'nullable|integer|min:1',
            'min_target_reps' => 'nullable|integer|min:1',
            'max_target_reps' => 'nullable|integer|min:1|gte:min_target_reps',
            'target_weight' => 'nullable|numeric|min:0',
            'rest_seconds' => 'nullable|integer|min:0',
        ];
    }
}
