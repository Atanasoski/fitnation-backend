<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Partner>
 */
class PartnerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'domain' => fake()->optional()->domainName(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the partner is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Gym pays Fit Nation — its members get app access without subscribing.
     */
    public function sponsor(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => \App\Enums\PartnerPlan::Sponsor,
            'plan_expires_at' => null,
        ]);
    }

    /**
     * A sponsorship that has already lapsed — members no longer get free access.
     */
    public function sponsorExpired(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => \App\Enums\PartnerPlan::Sponsor,
            'plan_expires_at' => now()->subDay(),
        ]);
    }
}
