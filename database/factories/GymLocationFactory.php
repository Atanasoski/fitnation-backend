<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GymLocation>
 */
class GymLocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'label' => fake()->streetName(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'radius_m' => 150,
        ];
    }
}
