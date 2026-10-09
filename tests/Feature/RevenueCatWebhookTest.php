<?php

namespace Tests\Feature;

use App\Enums\SubscriptionPeriodType;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Webhooks\RevenueCat\ProcessRevenueCatWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\WebhookClient\Models\WebhookCall;
use Tests\TestCase;

class RevenueCatWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $overrides */
    private function eventPayload(array $overrides = []): array
    {
        return [
            'api_version' => '1.0',
            'event' => array_merge([
                'type' => 'INITIAL_PURCHASE',
                'id' => (string) Str::uuid(),
                'app_user_id' => '1',
                'product_id' => 'com.fitnation.app.premium.monthly',
                'store' => 'APP_STORE',
                'environment' => 'PRODUCTION',
                'period_type' => 'NORMAL',
                'purchased_at_ms' => now()->subMinute()->getTimestampMs(),
                'expiration_at_ms' => now()->addMonth()->getTimestampMs(),
                'event_timestamp_ms' => now()->getTimestampMs(),
                'price' => 4.99,
                'currency' => 'USD',
            ], $overrides),
        ];
    }

    private function postWebhook(array $payload, ?string $secret = self::SECRET)
    {
        $headers = $secret !== null ? ['Authorization' => "Bearer {$secret}"] : [];

        return $this->postJson('/api/webhooks/revenuecat', $payload, $headers);
    }

    /**
     * Run the processor job directly against a stored webhook call — used for
     * handler-level cases where asserting on the HTTP layer adds nothing.
     */
    private function runJob(array $payload): void
    {
        $call = WebhookCall::create([
            'name' => 'revenuecat',
            'url' => '/api/webhooks/revenuecat',
            'payload' => $payload,
        ]);

        (new ProcessRevenueCatWebhook($call))->handle();
    }

    // ------------------------------------------------------------------
    // Signature validation (HTTP layer)
    // ------------------------------------------------------------------

    public function test_valid_signature_stores_call_and_creates_subscription(): void
    {
        $user = User::factory()->create();

        $response = $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'period_type' => 'TRIAL',
            'store' => 'PLAY_STORE',
        ]));

        $response->assertOk();
        $this->assertDatabaseCount('webhook_calls', 1);

        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertNotNull($subscription);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(SubscriptionPeriodType::Trial, $subscription->period_type);
        $this->assertSame('com.fitnation.app.premium.monthly', $subscription->product_id);
        $this->assertSame('play_store', $subscription->store->value);
        $this->assertTrue($subscription->expires_at->isFuture());
    }

    public function test_timestamps_survive_the_app_timezone(): void
    {
        // Step 15 finding #3: with APP_TIMEZONE=Europe/Skopje a 3-minute test
        // trial came back expired two hours ago.
        $user = User::factory()->create();
        $purchasedAtMs = now()->getTimestampMs();
        $expiresAtMs = now()->addMinutes(3)->getTimestampMs();

        $this->runJob($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'purchased_at_ms' => $purchasedAtMs,
            'expiration_at_ms' => $expiresAtMs,
        ]));

        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertSame(intdiv($purchasedAtMs, 1000), $subscription->purchased_at->getTimestamp());
        $this->assertSame(intdiv($expiresAtMs, 1000), $subscription->expires_at->getTimestamp());
        $this->assertTrue($subscription->isActive());
    }

    public function test_wrong_secret_is_rejected_before_processing(): void
    {
        User::factory()->create();

        $response = $this->postWebhook($this->eventPayload(), 'wrong-secret');

        $response->assertServerError();
        $this->assertDatabaseCount('webhook_calls', 0);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_missing_auth_header_is_rejected(): void
    {
        $response = $this->postWebhook($this->eventPayload(), null);

        $response->assertServerError();
        $this->assertDatabaseCount('webhook_calls', 0);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    // ------------------------------------------------------------------
    // Guards and short-circuits
    // ------------------------------------------------------------------

    public function test_test_event_returns_ok_without_touching_subscriptions(): void
    {
        $response = $this->postWebhook([
            'api_version' => '1.0',
            'event' => ['type' => 'TEST', 'id' => (string) Str::uuid()],
        ]);

        $response->assertOk();
        $this->assertDatabaseCount('webhook_calls', 1);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_sandbox_event_is_skipped_in_production(): void
    {
        $user = User::factory()->create();
        $this->app['env'] = 'production';

        $response = $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'environment' => 'SANDBOX',
        ]));

        $this->app['env'] = 'testing';

        $response->assertOk();
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_unknown_event_type_is_logged_and_ignored(): void
    {
        $user = User::factory()->create();

        $response = $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'SOME_FUTURE_EVENT',
        ]));

        $response->assertOk();
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_unresolvable_user_fails_the_job_for_replay(): void
    {
        $response = $this->postWebhook($this->eventPayload([
            'app_user_id' => '$RCAnonymousID:abc123',
            'aliases' => ['$RCAnonymousID:abc123'],
        ]));

        $response->assertServerError();
        $this->assertDatabaseCount('webhook_calls', 1);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_purchase_without_product_id_is_rejected_without_retry(): void
    {
        $user = User::factory()->create();

        $response = $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'product_id' => null,
        ]));

        // 200, not a thrown job: a malformed payload must not burn the retries.
        $response->assertOk();
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertStringContainsString('product_id is missing', WebhookCall::first()->exception['message']);
    }

    public function test_purchase_from_unknown_store_is_rejected_without_retry(): void
    {
        $user = User::factory()->create();

        $response = $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'store' => 'STRIPE',
        ]));

        $response->assertOk();
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertStringContainsString('STRIPE', WebhookCall::first()->exception['message']);
    }

    public function test_user_is_resolved_via_aliases_when_app_user_id_is_anonymous(): void
    {
        $user = User::factory()->create();

        $response = $this->postWebhook($this->eventPayload([
            'app_user_id' => '$RCAnonymousID:abc123',
            'aliases' => ['$RCAnonymousID:abc123', (string) $user->id],
        ]));

        $response->assertOk();
        $this->assertNotNull(Subscription::where('user_id', $user->id)->first());
    }

    // ------------------------------------------------------------------
    // Purchase lifecycle (job layer)
    // ------------------------------------------------------------------

    public function test_initial_purchase_freezes_acquisition_partner(): void
    {
        $partner = \App\Models\Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);

        $this->runJob($this->eventPayload(['app_user_id' => (string) $user->id]));

        $this->assertSame($partner->id, Subscription::where('user_id', $user->id)->first()->partner_id);
    }

    public function test_duplicate_delivery_is_idempotent(): void
    {
        $user = User::factory()->create();
        $payload = $this->eventPayload(['app_user_id' => (string) $user->id]);

        $this->runJob($payload);
        $this->runJob($payload); // identical event_timestamp_ms → skipped

        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_out_of_order_expiration_cannot_revoke_a_renewal(): void
    {
        $user = User::factory()->create();
        $renewalTs = now()->getTimestampMs();

        $this->runJob($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'RENEWAL',
            'event_timestamp_ms' => $renewalTs,
        ]));

        // A late EXPIRATION generated BEFORE the renewal arrives afterwards.
        $this->runJob($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'EXPIRATION',
            'event_timestamp_ms' => $renewalTs - 60_000,
        ]));

        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($user->fresh()->hasAppAccess());
    }

    public function test_renewal_converts_trial_to_normal_period(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->trial()->create([
            'user_id' => $user->id,
            'last_event_at_ms' => now()->subHour()->getTimestampMs(),
        ]);

        $this->runJob($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'RENEWAL',
        ]));

        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertSame(SubscriptionPeriodType::Normal, $subscription->period_type);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
    }

    public function test_cancellation_keeps_access_until_expiry(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'last_event_at_ms' => now()->subHour()->getTimestampMs(),
        ]);

        $this->runJob($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'CANCELLATION',
            'cancel_reason' => 'UNSUBSCRIBE',
        ]));

        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertTrue($user->fresh()->hasAppAccess());
    }

    public function test_refund_revokes_access_immediately(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'last_event_at_ms' => now()->subHour()->getTimestampMs(),
        ]);

        $this->runJob($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'CANCELLATION',
            'cancel_reason' => 'CUSTOMER_SUPPORT',
        ]));

        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertSame(SubscriptionStatus::Expired, $subscription->status);
        $this->assertFalse($user->fresh()->hasAppAccess());
    }

    public function test_billing_issue_keeps_access_for_the_paid_period(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'last_event_at_ms' => now()->subHour()->getTimestampMs(),
        ]);

        $this->runJob($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'BILLING_ISSUE',
        ]));

        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertSame(SubscriptionStatus::BillingIssue, $subscription->status);
        $this->assertTrue($user->fresh()->hasAppAccess());
    }

    public function test_expiration_revokes_access(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->cancelled()->create([
            'user_id' => $user->id,
            'last_event_at_ms' => now()->subHour()->getTimestampMs(),
        ]);

        $this->runJob($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'EXPIRATION',
        ]));

        $this->assertSame(
            SubscriptionStatus::Expired,
            Subscription::where('user_id', $user->id)->first()->status,
        );
    }

    public function test_uncancellation_reactivates(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->cancelled()->create([
            'user_id' => $user->id,
            'last_event_at_ms' => now()->subHour()->getTimestampMs(),
        ]);

        $this->runJob($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'UNCANCELLATION',
        ]));

        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNull($subscription->cancelled_at);
    }

    public function test_price_change_does_not_touch_the_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'last_event_at_ms' => now()->subHour()->getTimestampMs(),
        ]);
        $originalExpiry = $subscription->expires_at;

        $this->runJob($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'PRICE_CHANGE',
            'expiration_at_ms' => null,
        ]));

        $fresh = $subscription->fresh();
        $this->assertSame(SubscriptionStatus::Active, $fresh->status);
        $this->assertTrue($originalExpiry->equalTo($fresh->expires_at));
    }

    // ------------------------------------------------------------------
    // Transfer
    // ------------------------------------------------------------------

    public function test_transfer_repoints_subscription_to_target_user(): void
    {
        $source = User::factory()->create();
        $target = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $source->id]);

        $this->runJob([
            'api_version' => '1.0',
            'event' => [
                'type' => 'TRANSFER',
                'id' => (string) Str::uuid(),
                'transferred_from' => [(string) $source->id],
                'transferred_to' => [(string) $target->id],
                'event_timestamp_ms' => now()->getTimestampMs(),
            ],
        ]);

        $this->assertSame($target->id, $subscription->fresh()->user_id);
        $this->assertTrue($target->fresh()->hasAppAccess());
        $this->assertFalse($source->fresh()->hasAppAccess());
    }

    public function test_transfer_replaces_targets_superseded_subscription(): void
    {
        $source = User::factory()->create();
        $target = User::factory()->create();
        $moved = Subscription::factory()->create(['user_id' => $source->id]);
        $superseded = Subscription::factory()->expired()->create(['user_id' => $target->id]);

        $this->runJob([
            'api_version' => '1.0',
            'event' => [
                'type' => 'TRANSFER',
                'id' => (string) Str::uuid(),
                'transferred_from' => [(string) $source->id],
                'transferred_to' => [(string) $target->id],
                'event_timestamp_ms' => now()->getTimestampMs(),
            ],
        ]);

        $this->assertDatabaseMissing('subscriptions', ['id' => $superseded->id]);
        $this->assertSame($target->id, $moved->fresh()->user_id);
    }

    public function test_transfer_to_unknown_user_throws_for_replay(): void
    {
        $source = User::factory()->create();
        Subscription::factory()->create(['user_id' => $source->id]);

        $this->expectException(RuntimeException::class);

        $this->runJob([
            'api_version' => '1.0',
            'event' => [
                'type' => 'TRANSFER',
                'id' => (string) Str::uuid(),
                'transferred_from' => [(string) $source->id],
                'transferred_to' => ['$RCAnonymousID:nobody'],
                'event_timestamp_ms' => now()->getTimestampMs(),
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // End to end: webhook → API response
    // ------------------------------------------------------------------

    public function test_play_purchase_with_base_plan_grants_access_on_the_user_endpoint(): void
    {
        $user = User::factory()->create();

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'store' => 'PLAY_STORE',
            'product_id' => 'com.fitnation.app.premium.monthly:monthly',
            'period_type' => 'TRIAL',
        ]))->assertOk();

        $this->assertSame('com.fitnation.app.premium.monthly:monthly', Subscription::where('user_id', $user->id)->value('product_id'));

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.entitlements', ['app_access']);
    }

    public function test_purchase_webhook_flows_through_to_the_user_endpoint(): void
    {
        $user = User::factory()->create();

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'period_type' => 'TRIAL',
        ]))->assertOk();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.entitlements', ['app_access'])
            ->assertJsonPath('user.subscription.status', 'active')
            ->assertJsonPath('user.subscription.is_trial', true);
    }

    // ------------------------------------------------------------------
    // Characterization: how each event writes the row (locked before the
    // apply-subscription-state refactor, ticket 026/01)
    // ------------------------------------------------------------------

    public function test_renewal_keeps_the_known_price_original_purchase_and_partner(): void
    {
        $original = \App\Models\Partner::factory()->create();
        $current = \App\Models\Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $current->id]);
        $purchasedAt = now()->subMonths(2)->startOfSecond();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'partner_id' => $original->id,
            'price' => 9.99,
            'currency' => 'EUR',
            'purchased_at' => $purchasedAt,
            'cancelled_at' => now()->subDay(),
            'status' => SubscriptionStatus::Cancelled,
            'last_event_at_ms' => now()->subHour()->getTimestampMs(),
        ]);
        $expiresAtMs = now()->addMonths(2)->getTimestampMs();

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'RENEWAL',
            'product_id' => 'com.fitnation.app.premium.yearly',
            'store' => 'PLAY_STORE',
            'price' => null,
            'currency' => null,
            'expiration_at_ms' => $expiresAtMs,
        ]))->assertOk();

        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertSame($original->id, $subscription->partner_id);
        $this->assertSame('com.fitnation.app.premium.yearly', $subscription->product_id);
        $this->assertSame('play_store', $subscription->store->value);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame('9.99', $subscription->price);
        $this->assertSame('EUR', $subscription->currency);
        $this->assertTrue($purchasedAt->equalTo($subscription->purchased_at));
        $this->assertSame(intdiv($expiresAtMs, 1000), $subscription->expires_at->getTimestamp());
        $this->assertNull($subscription->cancelled_at);
    }

    public function test_renewal_without_a_row_creates_one_under_the_users_partner(): void
    {
        $partner = \App\Models\Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'RENEWAL',
            'period_type' => 'TRIAL',
        ]))->assertOk();

        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertSame($partner->id, $subscription->partner_id);
        $this->assertSame(SubscriptionPeriodType::Normal, $subscription->period_type);
        $this->assertSame('4.99', $subscription->price);
        $this->assertNotNull($subscription->purchased_at);
    }

    public function test_initial_purchase_over_an_old_row_starts_a_new_purchase(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->expired()->create([
            'user_id' => $user->id,
            'price' => 9.99,
            'cancelled_at' => now()->subMonth(),
            'last_event_at_ms' => now()->subHour()->getTimestampMs(),
        ]);
        $purchasedAtMs = now()->subMinute()->getTimestampMs();

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'purchased_at_ms' => $purchasedAtMs,
            'price' => null,
            'environment' => 'SANDBOX',
        ]))->assertOk();

        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNull($subscription->price);
        $this->assertNull($subscription->cancelled_at);
        $this->assertSame('sandbox', $subscription->environment);
        $this->assertSame(intdiv($purchasedAtMs, 1000), $subscription->purchased_at->getTimestamp());
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_refund_ends_the_period_now(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'last_event_at_ms' => now()->subHour()->getTimestampMs(),
        ]);

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'CANCELLATION',
            'cancel_reason' => 'CUSTOMER_SUPPORT',
        ]))->assertOk();

        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertFalse($subscription->expires_at->isFuture());
        $this->assertNotNull($subscription->cancelled_at);
    }

    public function test_pause_keeps_access_and_resume_reactivates(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->cancelled()->create([
            'user_id' => $user->id,
            'last_event_at_ms' => now()->subHour()->getTimestampMs(),
        ]);

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'SUBSCRIPTION_PAUSED',
            'event_timestamp_ms' => now()->subMinutes(2)->getTimestampMs(),
        ]))->assertOk();

        $this->assertSame(SubscriptionStatus::Paused, Subscription::where('user_id', $user->id)->first()->status);
        $this->assertTrue($user->fresh()->hasAppAccess());

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'SUBSCRIPTION_RESUMED',
        ]))->assertOk();

        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNull($subscription->cancelled_at);
    }

    public function test_status_events_without_a_subscription_create_nothing(): void
    {
        $user = User::factory()->create();

        foreach (['CANCELLATION', 'UNCANCELLATION', 'EXPIRATION', 'BILLING_ISSUE', 'SUBSCRIPTION_PAUSED'] as $i => $type) {
            $this->postWebhook($this->eventPayload([
                'app_user_id' => (string) $user->id,
                'type' => $type,
                'event_timestamp_ms' => now()->getTimestampMs() + $i,
            ]))->assertOk();
        }

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_each_applied_event_moves_the_high_water_mark(): void
    {
        $user = User::factory()->create();
        $purchaseTs = now()->subMinutes(10)->getTimestampMs();
        $cancelTs = now()->subMinutes(5)->getTimestampMs();

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'event_timestamp_ms' => $purchaseTs,
        ]))->assertOk();
        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'CANCELLATION',
            'event_timestamp_ms' => $cancelTs,
        ]))->assertOk();
        // Older than the cancellation, newer than the purchase: skipped.
        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'UNCANCELLATION',
            'event_timestamp_ms' => $cancelTs - 1,
        ]))->assertOk();

        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->status);
        $this->assertSame($cancelTs, $subscription->last_event_at_ms);
    }

    public function test_price_change_still_moves_the_high_water_mark(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'last_event_at_ms' => now()->subHour()->getTimestampMs(),
        ]);
        $ts = now()->getTimestampMs();

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'PRICE_CHANGE',
            'event_timestamp_ms' => $ts,
        ]))->assertOk();

        $this->assertSame($ts, $subscription->fresh()->last_event_at_ms);
    }

    // ------------------------------------------------------------------
    // Access lasts as long as the store says (026/02): webhook → gated route
    // ------------------------------------------------------------------

    /** A cheap gated endpoint (behind RequiresSubscription). */
    private const GATED_ROUTE = '/api/muscle-groups';

    /** A user with a paid subscription that ends in a day, created by webhook. */
    private function subscriberEndingTomorrow(): User
    {
        $user = User::factory()->create();

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'expiration_at_ms' => now()->addDay()->getTimestampMs(),
            'event_timestamp_ms' => now()->subMinute()->getTimestampMs(),
        ]))->assertOk();

        return $user;
    }

    private function assertGate(User $user, int $status): void
    {
        // fresh(): actingAs reuses the instance, and its loaded subscription would be stale.
        $this->actingAs($user->fresh(), 'sanctum')->getJson(self::GATED_ROUTE)->assertStatus($status);
    }

    public function test_billing_issue_keeps_access_until_the_stores_grace_period_ends(): void
    {
        $user = $this->subscriberEndingTomorrow();

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'BILLING_ISSUE',
            'expiration_at_ms' => now()->addDay()->getTimestampMs(),
            'grace_period_expiration_at_ms' => now()->addDays(16)->getTimestampMs(),
        ]))->assertOk();

        $this->travel(15)->days();
        $this->assertGate($user, 200);

        $this->travel(2)->days();
        $this->assertGate($user, 403);
    }

    public function test_billing_issue_without_a_grace_period_keeps_access_until_the_events_expiration(): void
    {
        $user = $this->subscriberEndingTomorrow();

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'BILLING_ISSUE',
            'expiration_at_ms' => now()->addDays(3)->getTimestampMs(),
            'grace_period_expiration_at_ms' => null,
        ]))->assertOk();

        $this->travel(2)->days();
        $this->assertGate($user, 200);

        $this->travel(2)->days();
        $this->assertGate($user, 403);
    }

    public function test_an_extended_subscription_keeps_access_until_the_new_expiration(): void
    {
        $user = $this->subscriberEndingTomorrow();

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'SUBSCRIPTION_EXTENDED',
            'expiration_at_ms' => now()->addDays(10)->getTimestampMs(),
        ]))->assertOk();

        $this->travel(9)->days();
        $this->assertGate($user, 200);

        $this->travel(2)->days();
        $this->assertGate($user, 403);
    }

    public function test_a_temporary_entitlement_grant_gives_access_until_its_expiration(): void
    {
        $user = $this->subscriberEndingTomorrow();

        $this->postWebhook($this->temporaryGrant($user, [
            'expiration_at_ms' => now()->addDays(2)->getTimestampMs(),
        ]))->assertOk();

        $this->travel(36)->hours();
        $this->assertGate($user, 200);

        $this->travel(1)->days();
        $this->assertGate($user, 403);
    }

    public function test_an_extension_keeps_a_cancelled_subscription_cancelled(): void
    {
        $user = $this->subscriberEndingTomorrow();

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'CANCELLATION',
            'cancel_reason' => 'UNSUBSCRIBE',
            'event_timestamp_ms' => now()->getTimestampMs() - 1,
        ]))->assertOk();

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'SUBSCRIPTION_EXTENDED',
            'expiration_at_ms' => now()->addDays(10)->getTimestampMs(),
        ]))->assertOk();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertJsonPath('user.subscription.status', 'cancelled');

        $this->travel(9)->days();
        $this->assertGate($user, 200);
    }

    public function test_stale_billing_issue_and_extension_events_cannot_cut_access_short(): void
    {
        $user = User::factory()->create();
        $renewedAt = now()->getTimestampMs();

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'RENEWAL',
            'expiration_at_ms' => now()->addDays(30)->getTimestampMs(),
            'event_timestamp_ms' => $renewedAt,
        ]))->assertOk();

        // Both generated before the renewal, delivered after it.
        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'BILLING_ISSUE',
            'expiration_at_ms' => now()->addDay()->getTimestampMs(),
            'grace_period_expiration_at_ms' => now()->addDays(3)->getTimestampMs(),
            'event_timestamp_ms' => $renewedAt - 60_000,
        ]))->assertOk();
        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'SUBSCRIPTION_EXTENDED',
            'expiration_at_ms' => now()->addDays(2)->getTimestampMs(),
            'event_timestamp_ms' => $renewedAt - 30_000,
        ]))->assertOk();

        $this->travel(20)->days();
        $this->assertGate($user, 200);
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertJsonPath('user.subscription.status', 'active');
    }

    /** RevenueCat's TEMPORARY_ENTITLEMENT_GRANT, as its sample shows it: no product, no expiration. */
    private function temporaryGrant(User $user, array $overrides = []): array
    {
        return [
            'api_version' => '1.0',
            'event' => array_merge([
                'type' => 'TEMPORARY_ENTITLEMENT_GRANT',
                'id' => (string) Str::uuid(),
                'app_user_id' => (string) $user->id,
                'store' => 'APP_STORE',
                'event_timestamp_ms' => now()->getTimestampMs(),
            ], $overrides),
        ];
    }

    public function test_a_temporary_entitlement_grant_without_an_expiration_gives_a_day_of_access(): void
    {
        $user = $this->subscriberEndingTomorrow();
        $this->travel(24 * 60 + 1)->minutes();
        $this->assertGate($user, 403);

        $this->postWebhook($this->temporaryGrant($user))->assertOk();

        $this->travel(23)->hours();
        $this->assertGate($user, 200);

        $this->travel(2)->hours();
        $this->assertGate($user, 403);
    }

    public function test_a_temporary_entitlement_grant_never_shortens_a_longer_subscription(): void
    {
        $user = User::factory()->create();
        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'expiration_at_ms' => now()->addDays(30)->getTimestampMs(),
            'event_timestamp_ms' => now()->subMinute()->getTimestampMs(),
        ]))->assertOk();

        $this->postWebhook($this->temporaryGrant($user, [
            'expiration_at_ms' => now()->addDay()->getTimestampMs(),
        ]))->assertOk();

        $this->travel(20)->days();
        $this->assertGate($user, 200);
    }

    public function test_an_extension_after_expiration_reopens_access_until_the_new_date(): void
    {
        $user = $this->subscriberEndingTomorrow();
        $this->travel(24 * 60 + 1)->minutes();

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'EXPIRATION',
            'event_timestamp_ms' => now()->getTimestampMs(),
        ]))->assertOk();
        $this->assertGate($user, 403);

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'SUBSCRIPTION_EXTENDED',
            'expiration_at_ms' => now()->addDays(5)->getTimestampMs(),
            'event_timestamp_ms' => now()->getTimestampMs() + 1,
        ]))->assertOk();

        $this->travel(4)->days();
        $this->assertGate($user, 200);

        $this->travel(2)->days();
        $this->assertGate($user, 403);
    }

    // ------------------------------------------------------------------
    // Webhook robustness (026/03): retries, lookup, event data
    // ------------------------------------------------------------------

    public function test_a_failed_webhook_is_retried_five_times_with_growing_delays(): void
    {
        $call = WebhookCall::create(['name' => 'revenuecat', 'url' => '/api/webhooks/revenuecat', 'payload' => []]);
        $job = new ProcessRevenueCatWebhook($call);

        $this->assertSame(5, $job->tries);
        $this->assertSame([60, 300, 1800, 3600], $job->backoff());
    }

    private function assertEntitled(User $user, bool $entitled): void
    {
        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.entitlements', $entitled ? ['app_access'] : []);
    }

    public function test_the_app_user_id_wins_over_the_original_id_and_aliases(): void
    {
        // Created first, so the lower ids: a lowest-id lookup would pick them.
        $original = User::factory()->create();
        $alias = User::factory()->create();
        $buyer = User::factory()->create();

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $buyer->id,
            'original_app_user_id' => (string) $original->id,
            'aliases' => [(string) $alias->id, (string) $original->id, (string) $buyer->id],
        ]))->assertOk();

        $this->assertEntitled($buyer, true);
        $this->assertEntitled($original, false);
        $this->assertEntitled($alias, false);
    }

    public function test_the_original_id_wins_over_aliases_when_the_app_user_id_is_anonymous(): void
    {
        $alias = User::factory()->create();
        $original = User::factory()->create();

        $this->postWebhook($this->eventPayload([
            'app_user_id' => '$RCAnonymousID:abc123',
            'original_app_user_id' => (string) $original->id,
            'aliases' => [(string) $alias->id, '$RCAnonymousID:abc123', (string) $original->id],
        ]))->assertOk();

        $this->assertEntitled($original, true);
        $this->assertEntitled($alias, false);
    }

    public function test_an_app_user_id_with_no_account_falls_through_to_the_next_id(): void
    {
        $original = User::factory()->create();

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) ($original->id + 1000),
            'original_app_user_id' => (string) $original->id,
        ]))->assertOk();

        $this->assertEntitled($original, true);
    }

    private const MONTHLY = 'com.fitnation.app.premium.monthly';

    private const YEARLY = 'com.fitnation.app.premium.yearly';

    /** RevenueCat sends the old product in product_id and the new one in new_product_id. */
    private function productChange(User $user, array $overrides = []): array
    {
        return $this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'PRODUCT_CHANGE',
            'product_id' => self::MONTHLY,
            'new_product_id' => self::YEARLY,
            ...$overrides,
        ]);
    }

    public function test_a_product_change_records_the_new_product(): void
    {
        $user = $this->subscriberEndingTomorrow();

        $this->postWebhook($this->productChange($user))->assertOk();

        $this->assertSame(self::YEARLY, Subscription::where('user_id', $user->id)->value('product_id'));
        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/user')
            ->assertJsonPath('user.subscription.status', 'active')
            ->assertJsonPath('user.entitlements', ['app_access']);
    }

    public function test_a_product_change_without_a_new_product_id_records_the_events_product(): void
    {
        $user = $this->subscriberEndingTomorrow();

        $this->postWebhook($this->productChange($user, [
            'store' => 'PLAY_STORE',
            'product_id' => self::YEARLY.':yearly',
            'new_product_id' => null,
        ]))->assertOk();

        $this->assertSame(self::YEARLY.':yearly', Subscription::where('user_id', $user->id)->value('product_id'));
    }

    public function test_a_product_change_does_not_clear_a_pending_cancellation(): void
    {
        $user = $this->subscriberEndingTomorrow();
        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'CANCELLATION',
            'cancel_reason' => 'UNSUBSCRIBE',
            'event_timestamp_ms' => now()->subSeconds(30)->getTimestampMs(),
        ]))->assertOk();

        $this->postWebhook($this->productChange($user))->assertOk();

        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertSame(self::YEARLY, $subscription->product_id);
        $this->assertNotNull($subscription->cancelled_at);
        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/user')
            ->assertJsonPath('user.subscription.status', 'cancelled')
            ->assertJsonPath('user.entitlements', ['app_access']);
    }

    public function test_a_cancellation_records_when_it_happened_not_when_it_was_processed(): void
    {
        $user = User::factory()->create();
        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'event_timestamp_ms' => now()->subDays(3)->getTimestampMs(),
        ]))->assertOk();
        $cancelledAtMs = now()->subDays(2)->getTimestampMs();

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'CANCELLATION',
            'cancel_reason' => 'UNSUBSCRIBE',
            'event_timestamp_ms' => $cancelledAtMs,
        ]))->assertOk();

        $this->assertSame(intdiv($cancelledAtMs, 1000), Subscription::where('user_id', $user->id)->first()->cancelled_at->getTimestamp());
    }

    public function test_a_refund_ends_access_when_it_happened_not_when_it_was_processed(): void
    {
        $user = User::factory()->create();
        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'event_timestamp_ms' => now()->subDays(3)->getTimestampMs(),
        ]))->assertOk();
        $refundedAtMs = now()->subDays(2)->getTimestampMs();

        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'CANCELLATION',
            'cancel_reason' => 'CUSTOMER_SUPPORT',
            'event_timestamp_ms' => $refundedAtMs,
        ]))->assertOk();

        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertSame(intdiv($refundedAtMs, 1000), $subscription->cancelled_at->getTimestamp());
        $this->assertSame(intdiv($refundedAtMs, 1000), $subscription->expires_at->getTimestamp());
    }

    public function test_a_new_purchase_on_an_existing_row_keeps_the_acquisition_partner(): void
    {
        $acquiredVia = \App\Models\Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $acquiredVia->id]);
        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'event_timestamp_ms' => now()->subMonths(2)->getTimestampMs(),
        ]))->assertOk();
        $user->update(['partner_id' => \App\Models\Partner::factory()->create()->id]);

        // The old subscription lapsed; the user buys again under the new partner.
        $this->postWebhook($this->eventPayload([
            'app_user_id' => (string) $user->id,
            'type' => 'EXPIRATION',
            'event_timestamp_ms' => now()->subMonth()->getTimestampMs(),
        ]))->assertOk();
        $this->postWebhook($this->eventPayload(['app_user_id' => (string) $user->id]))->assertOk();

        $this->assertSame($acquiredVia->id, Subscription::where('user_id', $user->id)->value('partner_id'));
    }

    private function transferPayload(User $from, User $to): array
    {
        return [
            'api_version' => '1.0',
            'event' => [
                'type' => 'TRANSFER',
                'id' => (string) Str::uuid(),
                'transferred_from' => [(string) $from->id],
                'transferred_to' => [(string) $to->id],
                'event_timestamp_ms' => now()->getTimestampMs(),
            ],
        ];
    }

    public function test_a_transfer_keeps_the_receivers_active_subscription_over_an_expired_one(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();
        $transferred = Subscription::factory()->expired()->create(['user_id' => $from->id]);
        $receivers = Subscription::factory()->create(['user_id' => $to->id, 'expires_at' => now()->addMonth()]);

        $this->postWebhook($this->transferPayload($from, $to))->assertOk();

        $this->assertSame($receivers->id, Subscription::where('user_id', $to->id)->value('id'));
        $this->assertEntitled($to, true);
        $this->assertDatabaseHas('subscriptions', ['id' => $transferred->id]);
    }

    public function test_a_transfer_of_an_active_subscription_replaces_the_receivers_active_one(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();
        $transferred = Subscription::factory()->create(['user_id' => $from->id, 'expires_at' => now()->addYear()]);
        $receivers = Subscription::factory()->create(['user_id' => $to->id, 'expires_at' => now()->addMonth()]);

        $this->postWebhook($this->transferPayload($from, $to))->assertOk();

        $this->assertSame($transferred->id, Subscription::where('user_id', $to->id)->value('id'));
        $this->assertDatabaseMissing('subscriptions', ['id' => $receivers->id]);
        $this->assertEntitled($to, true);
        $this->assertEntitled($from, false);
    }
}
