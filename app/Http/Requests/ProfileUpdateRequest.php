<?php

namespace App\Http\Requests;

use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Enums\TrainingExperience;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'fitness_goal' => ['nullable', Rule::enum(FitnessGoal::class)],
            'age' => ['nullable', 'integer', 'min:1', 'max:150'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'training_experience' => ['nullable', Rule::enum(TrainingExperience::class)],
            'training_days_per_week' => ['nullable', 'integer', 'min:1', 'max:7'],
            'workout_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please provide your name.',
            'name.max' => 'Your name cannot exceed 255 characters.',
            'email.required' => 'Please provide your email address.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already in use.',
            'age.integer' => 'Please provide a valid age.',
            'age.min' => 'Age must be at least 1.',
            'age.max' => 'Age must not exceed 150.',
            'training_days_per_week.integer' => 'Please provide a valid number of training days.',
            'training_days_per_week.min' => 'Training days must be at least 1.',
            'training_days_per_week.max' => 'Training days cannot exceed 7.',
            'workout_duration_minutes.integer' => 'Please provide a valid workout duration.',
            'workout_duration_minutes.min' => 'Workout duration must be at least 1 minute.',
            'workout_duration_minutes.max' => 'Workout duration cannot exceed 600 minutes.',
        ];
    }
}
