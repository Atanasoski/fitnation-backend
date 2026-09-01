<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConvertsIncomingUnits;
use App\Services\WorkoutSession\SetOwnership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LogSetRequest extends FormRequest
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
        $this->convertMeasuredInputs('workout_session_set_logs', ['weight']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Optional so deployed clients that only know about exercise_id keep
            // working; the controller resolves the row when it is absent. Scoped
            // to the route's session so a row from another session is rejected.
            'workout_session_exercise_id' => [
                'nullable',
                'integer',
                Rule::exists('workout_session_exercises', 'id')
                    ->where('workout_session_id', $this->route('session')?->id),
            ],
            'exercise_id' => 'required|exists:workout_exercises,id',
            'set_number' => 'required|integer|min:1',
            'weight' => 'required|numeric|min:0',
            'reps' => 'required|integer|min:0',
            'rest_seconds' => 'nullable|integer|min:0',
        ];
    }

    /**
     * A row id and an exercise_id that disagree would file the set under one
     * exercise's row while attributing the reps to another — corrupting PR
     * detection, progression history and volume totals at once.
     */
    public function after(): array
    {
        return [
            function (\Illuminate\Validation\Validator $validator) {
                $session = $this->route('session');

                if (! $this->workout_session_exercise_id || $session === null || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $row = SetOwnership::forSession($session)
                    ->rowById((int) $this->workout_session_exercise_id);

                if ($row !== null && (int) $row->exercise_id !== (int) $this->exercise_id) {
                    $validator->errors()->add(
                        'exercise_id',
                        'The exercise does not match the session exercise the set is being logged against.'
                    );
                }
            },
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
            'workout_session_exercise_id.exists' => 'That exercise does not belong to this workout session.',
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
