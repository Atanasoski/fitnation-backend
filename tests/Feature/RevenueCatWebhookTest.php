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
}
