<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SwapWorkoutSessionExerciseRequest extends FormRequest
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
            'exercise_id' => 'required|integer|exists:workout_exercises,id',
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
        ];
    }
}
