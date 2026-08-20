<?php

namespace Database\Factories;

use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Enums\TrainingExperience;
use App\Enums\UnitSystem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserProfile>
 */
class UserProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fitness_goal' => fake()->randomElement(FitnessGoal::cases()),
            'age' => fake()->numberBetween(18, 80),
            'gender' => fake()->randomElement(Gender::cases()),
            'height' => fake()->numberBetween(150, 200),
            'weight' => fake()->randomFloat(2, 50, 150),
            'training_experience' => fake()->randomElement(TrainingExperience::cases()),
            'training_days_per_week' => fake()->numberBetween(1, 7),
            'workout_duration_minutes' => fake()->randomElement([30, 45, 60, 75, 90]),
            'unit_system' => UnitSystem::Metric,
        ];
    }

    /**
     * Indicate that the profile uses the imperial unit system.
     */
    public function imperial(): static
    {
        return $this->state(fn () => ['unit_system' => UnitSystem::Imperial]);
    }
}
