<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dismissal endpoint behind the Join Prompt (issue #89): a customer's
 * "Not interested" is recorded as a permanent decline and "Maybe later" as a
 * 24-hour snooze, one row per customer×Partner, upserted on repeat. A decline
 * is terminal: no later snooze may un-decline a Partner (GLOSSARY: Join
 * Prompt; ADR 0006).
 */
class JoinPromptDismissalTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_decline_is_recorded_for_the_customer_and_partner(): void
    {
        $partner = Partner::factory()->create();
        $customer = User::factory()->create();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/join-prompt/dismissals', [
                'partner_id' => $partner->id,
                'kind' => 'declined',
            ])
            ->assertNoContent();

        $this->assertDatabaseHas('join_prompt_dismissals', [
            'user_id' => $customer->id,
            'partner_id' => $partner->id,
            'kind' => 'declined',
            'snoozed_until' => null,
        ]);
    }

    public function test_a_snooze_is_recorded_until_24_hours_from_now(): void
    {
        $this->freezeTime();

        $partner = Partner::factory()->create();
        $customer = User::factory()->create();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/join-prompt/dismissals', [
                'partner_id' => $partner->id,
                'kind' => 'snoozed',
            ])
            ->assertNoContent();

        $this->assertDatabaseHas('join_prompt_dismissals', [
            'user_id' => $customer->id,
            'partner_id' => $partner->id,
            'kind' => 'snoozed',
            'snoozed_until' => now()->addHours(24),
        ]);
    }

    public function test_a_fresh_snooze_always_resets_the_24_hour_window(): void
    {
        $this->freezeTime();

        $partner = Partner::factory()->create();
        $customer = User::factory()->create();

        $this->dismiss($customer, $partner, 'snoozed');

        $this->travel(23)->hours();
        $this->dismiss($customer, $partner, 'snoozed');

        $this->assertDatabaseCount('join_prompt_dismissals', 1);
        $this->assertDatabaseHas('join_prompt_dismissals', [
            'user_id' => $customer->id,
            'partner_id' => $partner->id,
            'kind' => 'snoozed',
            'snoozed_until' => now()->addHours(24),
        ]);
    }

    public function test_a_decline_overwrites_a_snooze(): void
    {
        $partner = Partner::factory()->create();
        $customer = User::factory()->create();

        $this->dismiss($customer, $partner, 'snoozed');
        $this->dismiss($customer, $partner, 'declined');

        $this->assertDatabaseCount('join_prompt_dismissals', 1);
        $this->assertDatabaseHas('join_prompt_dismissals', [
            'user_id' => $customer->id,
            'partner_id' => $partner->id,
            'kind' => 'declined',
            'snoozed_until' => null,
        ]);
    }

    public function test_a_snooze_never_un_declines_a_partner(): void
    {
        $partner = Partner::factory()->create();
        $customer = User::factory()->create();

        $this->dismiss($customer, $partner, 'declined');

        // Accepted (the app may post an implicit snooze), but ignored:
        // declined is terminal per the glossary and ADR 0006.
        $this->dismiss($customer, $partner, 'snoozed');

        $this->assertDatabaseCount('join_prompt_dismissals', 1);
        $this->assertDatabaseHas('join_prompt_dismissals', [
            'user_id' => $customer->id,
            'partner_id' => $partner->id,
            'kind' => 'declined',
            'snoozed_until' => null,
        ]);
    }

    public function test_a_repeat_decline_stays_a_single_declined_row(): void
    {
        $partner = Partner::factory()->create();
        $customer = User::factory()->create();

        $this->dismiss($customer, $partner, 'declined');
        $this->dismiss($customer, $partner, 'declined');

        $this->assertDatabaseCount('join_prompt_dismissals', 1);
        $this->assertDatabaseHas('join_prompt_dismissals', [
            'user_id' => $customer->id,
            'partner_id' => $partner->id,
            'kind' => 'declined',
        ]);
    }

    public function test_the_partner_and_kind_are_validated(): void
    {
        $partner = Partner::factory()->create();
        $customer = User::factory()->create();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/join-prompt/dismissals', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['partner_id', 'kind']);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/join-prompt/dismissals', [
                'partner_id' => $partner->id + 1000,
                'kind' => 'declined',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['partner_id']);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/join-prompt/dismissals', [
                'partner_id' => $partner->id,
                'kind' => 'joined',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kind']);

        $this->assertDatabaseCount('join_prompt_dismissals', 0);
    }

    public function test_the_endpoint_requires_authentication(): void
    {
        $partner = Partner::factory()->create();

        $this->postJson('/api/join-prompt/dismissals', [
            'partner_id' => $partner->id,
            'kind' => 'declined',
        ])->assertUnauthorized();
    }

    private function dismiss(User $customer, Partner $partner, string $kind): void
    {
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/join-prompt/dismissals', [
                'partner_id' => $partner->id,
                'kind' => $kind,
            ])
            ->assertNoContent();
    }
}
