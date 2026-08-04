<?php

namespace App\Http\Requests\Api;

use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Enums\TrainingExperience;
use App\Enums\UnitSystem;
use App\Models\User;
use App\Services\UnitConversionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
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
     * Convert incoming weight/height to the canonical kg/cm storage units
     * before validation runs, based on the unit system this request (or the
     * user's stored profile) declares.
     */
    protected function prepareForValidation(): void
    {
        $unitSystem = UnitSystem::tryFrom((string) $this->input('unit_system'))
            ?? $this->user()->profile?->unit_system
            ?? UnitSystem::Metric;

        if ($unitSystem !== UnitSystem::Imperial) {
            return;
        }

        $merge = [];

        if ($this->filled('weight')) {
            $merge['weight'] = $this->conversionService->toKg((float) $this->input('weight'), UnitSystem::Imperial);
        }

        if ($this->filled('height')) {
            $merge['height'] = $this->conversionService->toCm((float) $this->input('height'), UnitSystem::Imperial);
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'profile_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'fitness_goal' => ['nullable', Rule::enum(FitnessGoal::class)],
            'age' => ['nullable', 'integer', 'min:1', 'max:150'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'height' => ['nullable', 'integer', 'min:50', 'max:300'],
            'weight' => ['nullable', 'numeric', 'min:1', 'max:500'],
            'training_experience' => ['nullable', Rule::enum(TrainingExperience::class)],
            'training_days_per_week' => ['nullable', 'integer', 'min:1', 'max:7'],
            'workout_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'unit_system' => ['sometimes', 'nullable', Rule::enum(UnitSystem::class)],
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
            'profile_photo.image' => 'The file must be an image.',
            'profile_photo.mimes' => 'The profile photo must be a file of type: jpeg, png, jpg, gif.',
            'profile_photo.max' => 'The profile photo must not exceed 2MB.',
            'age.integer' => 'Please provide a valid age.',
            'age.min' => 'Age must be at least 1.',
            'age.max' => 'Age must not exceed 150.',
            'height.integer' => 'Please provide a valid height in centimeters.',
            'height.min' => 'Height must be at least 50 cm.',
            'height.max' => 'Height must not exceed 300 cm.',
            'weight.numeric' => 'Please provide a valid weight.',
            'weight.min' => 'Weight must be at least 1 kg.',
            'weight.max' => 'Weight must not exceed 500 kg.',
            'training_days_per_week.integer' => 'Please provide a valid number of training days.',
            'training_days_per_week.min' => 'Training days must be at least 1.',
            'training_days_per_week.max' => 'Training days cannot exceed 7.',
            'workout_duration_minutes.integer' => 'Please provide a valid workout duration.',
            'workout_duration_minutes.min' => 'Workout duration must be at least 1 minute.',
            'workout_duration_minutes.max' => 'Workout duration cannot exceed 600 minutes.',
        ];
    }
}
