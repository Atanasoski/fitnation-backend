<?php

namespace Database\Factories;

use App\Enums\SubscriptionPeriodType;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionStore;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Default: an active, paid monthly subscription with 25 days remaining.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'partner_id' => null,
            'product_id' => 'com.fitnation.app.premium.monthly',
            'store' => SubscriptionStore::AppStore,
            'status' => SubscriptionStatus::Active,
            'period_type' => SubscriptionPeriodType::Normal,
            'price' => 4.99,
            'currency' => 'USD',
            'purchased_at' => now()->subDays(5),
            'expires_at' => now()->addDays(25),
            'cancelled_at' => null,
            'environment' => 'production',
        ];
    }

    public function yearly(): static
    {
        return $this->state(fn () => [
            'product_id' => 'com.fitnation.app.premium.yearly',
            'price' => 39.99,
            'expires_at' => now()->addDays(360),
        ]);
    }

    public function trial(): static
    {
        return $this->state(fn () => [
            'period_type' => SubscriptionPeriodType::Trial,
            'price' => null,
            'purchased_at' => now()->subDays(2),
            'expires_at' => now()->addDays(5),
        ]);
    }

    /** Auto-renew turned off; access continues until expires_at. */
    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now()->subDay(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Expired,
            'purchased_at' => now()->subDays(40),
            'expires_at' => now()->subDays(10),
        ]);
    }

    /** Store is retrying a failed charge; the current period is still paid. */
    public function billingIssue(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::BillingIssue,
        ]);
    }

    /** Android-only pause; takes effect at period end. */
    public function paused(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Paused,
        ]);
    }

    public function sandbox(): static
    {
        return $this->state(fn () => [
            'environment' => 'sandbox',
        ]);
    }
}
