<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConvertsIncomingUnits;
use App\Models\WorkoutTemplate;
use App\Rules\ExerciseThePlanOffers;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An exercise row added to a workout from the staff pages. The exercise comes
 * from the plan's catalogue; the target weight is entered in the plan owner's
 * Unit System (ADR-0001). Authorisation is the route's PlanPolicy guard.
 */
class StoreWorkoutTemplateExerciseRequest extends FormRequest
{
    use ConvertsIncomingUnits;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->convertMeasuredInputs('workout_template_exercises', ['target_weight'], $this->workout()->plan->ownerUnitSystem());
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'exercise_id' => ['required', 'integer', new ExerciseThePlanOffers($this->workout()->plan)],
            'order' => 'nullable|integer|min:0',
            'target_sets' => 'nullable|integer|min:0',
            'min_target_reps' => 'nullable|integer|min:1',
            'max_target_reps' => 'nullable|integer|min:1|gte:min_target_reps',
            'target_weight' => 'nullable|numeric|min:0',
            'rest_seconds' => 'nullable|integer|min:0',
        ];
    }

    private function workout(): WorkoutTemplate
    {
        return $this->route('workoutTemplate');
    }
}
