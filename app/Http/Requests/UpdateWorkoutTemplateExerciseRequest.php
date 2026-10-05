<?php

namespace App\Http\Requests;

use App\Enums\UnitSystem;
use App\Http\Requests\Concerns\ConvertsIncomingUnits;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An exercise row's targets, edited from the staff pages. Only the fields sent
 * change. The target weight is entered in the plan owner's Unit System
 * (ADR-0001); a blank one, sent, means no target. Authorisation is the route's
 * PlanPolicy guard.
 */
class UpdateWorkoutTemplateExerciseRequest extends FormRequest
{
    use ConvertsIncomingUnits;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('target_weight') && ! $this->filled('target_weight')) {
            $this->merge(['target_weight' => 0]);
        }

        $this->convertMeasuredInputs('workout_template_exercises', ['target_weight'], $this->ownersUnitSystem());
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order' => 'nullable|integer|min:0',
            'target_sets' => 'nullable|integer|min:0',
            'min_target_reps' => 'nullable|integer|min:1',
            'max_target_reps' => 'nullable|integer|min:1|gte:min_target_reps',
            'target_weight' => 'nullable|numeric|min:0',
            'rest_seconds' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Staff enter weights the way the plan's owner sees them; a library plan
     * has no owner and stays metric.
     */
    private function ownersUnitSystem(): UnitSystem
    {
        /** @var WorkoutTemplate $workout */
        $workout = $this->route('workoutTemplate');

        return $workout->plan->user?->unitSystem() ?? UnitSystem::Metric;
    }
}
