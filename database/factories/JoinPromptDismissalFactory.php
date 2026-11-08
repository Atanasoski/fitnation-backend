<?php

namespace Database\Factories;

use App\Enums\JoinPromptDismissalKind;
use App\Models\JoinPromptDismissal;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JoinPromptDismissal>
 */
class JoinPromptDismissalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'partner_id' => Partner::factory(),
            'kind' => JoinPromptDismissalKind::Snoozed,
            'snoozed_until' => now()->addHours(JoinPromptDismissal::SNOOZE_HOURS),
        ];
    }

    /** A permanent "Not interested". */
    public function declined(): static
    {
        return $this->state(fn () => [
            'kind' => JoinPromptDismissalKind::Declined,
            'snoozed_until' => null,
        ]);
    }

    /** A "Maybe later" with its 24-hour window still open. */
    public function snoozed(): static
    {
        return $this->state(fn () => [
            'kind' => JoinPromptDismissalKind::Snoozed,
            'snoozed_until' => now()->addHours(JoinPromptDismissal::SNOOZE_HOURS),
        ]);
    }
}
