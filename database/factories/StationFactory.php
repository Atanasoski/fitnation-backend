<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\Station;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Station>
 */
class StationFactory extends Factory
{
    protected $model = Station::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'name' => fake()->randomElement(['Cable Crossover', 'Leg Press', 'Squat Rack', 'Smith Machine', 'Bench Press']).' '.fake()->unique()->numberBetween(1, 999),
        ];
    }
}
