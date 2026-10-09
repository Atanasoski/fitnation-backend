<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionSyncTest extends TestCase
{
    use RefreshDatabase;

    private const GATED_ROUTE = '/api/muscle-groups';

    protected function setUp(): void
    {
        parent::setUp();

        // Whole seconds: RevenueCat's REST dates carry none, its webhooks carry ms.
        $this->travelTo(now()->startOfSecond());

        config(['services.revenuecat.secret_api_key' => 'sk_test_secret']);
    }

    /**
     * A RevenueCat REST v1 subscriber with the app_access entitlement backed
     * by one store subscription.
     *
     * @param  array<string, mixed>  $subscription  overrides for the subscription
     */
    private function subscriber(array $subscription = [], string $productId = 'com.fitnation.app.premium.monthly'): array
    {
        $sub = array_merge([
            'store' => 'app_store',
            'period_type' => 'normal',
            'original_purchase_date' => now()->subMinute()->toIso8601ZuluString(),
            'purchase_date' => now()->subMinute()->toIso8601ZuluString(),
            'expires_date' => now()->addMonth()->toIso8601ZuluString(),
            'unsubscribe_detected_at' => null,
            'billing_issues_detected_at' => null,
            'grace_period_expires_date' => null,
            'refunded_at' => null,
            'is_sandbox' => false,
            'ownership_type' => 'PURCHASED',
        ], $subscription);

        return [
            'request_date' => now()->toIso8601ZuluString(),
            'request_date_ms' => now()->getTimestampMs(),
            'subscriber' => [
                'original_app_user_id' => '1',
                'entitlements' => [
                    'app_access' => [
                        'expires_date' => $sub['expires_date'],
                        'grace_period_expires_date' => $sub['grace_period_expires_date'],
                        'product_identifier' => $productId,
                        'purchase_date' => $sub['purchase_date'],
                    ],
                ],
                'subscriptions' => [$productId => $sub],
                'non_subscriptions' => [],
            ],
        ];
    }

    private function fakeRevenueCat(array $body, int $status = 200): void
    {
        Http::fake(['api.revenuecat.com/*' => Http::response($body, $status)]);
    }

    private function postWebhook(array $event)
    {
        return $this->postJson('/api/webhooks/revenuecat', ['api_version' => '1.0', 'event' => array_merge([
            'id' => (string) Str::uuid(),
            'environment' => 'PRODUCTION',
            'store' => 'APP_STORE',
            'product_id' => 'com.fitnation.app.premium.monthly',
            'event_timestamp_ms' => now()->subSeconds(5)->getTimestampMs(),
        ], $event)], ['Authorization' => 'Bearer test-webhook-secret']);
    }

    /** The row's columns that describe the subscription itself. */
    private function rowOf(User $user, array $except = []): array
    {
        return collect(Subscription::where('user_id', $user->id)->firstOrFail()->getAttributes())
            ->except(['id', 'user_id', 'last_event_at_ms', 'created_at', 'updated_at', ...$except])
            ->all();
    }

    private function sync(User $user)
    {
        return $this->actingAs($user, 'sanctum')->postJson('/api/subscription/sync');
    }

    public function test_sync_records_the_subscription_revenuecat_reports_and_answers_like_get_user(): void
    {
        $user = User::factory()->create();
        $this->fakeRevenueCat($this->subscriber());

        $response = $this->sync($user);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.entitlements', ['app_access'])
            ->assertJsonPath('user.subscription.status', 'active');

        $this->assertSame(
            $this->actingAs($user, 'sanctum')->getJson('/api/user')->json(),
            $response->json(),
        );

        Http::assertSent(fn (Request $request) => $request->url() === "https://api.revenuecat.com/v1/subscribers/{$user->id}"
            && $request->hasHeader('Authorization', 'Bearer sk_test_secret'));
    }

    public function test_a_blocked_user_can_sync_and_is_let_in(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->getJson(self::GATED_ROUTE)->assertForbidden();
        $this->fakeRevenueCat($this->subscriber());

        $this->sync($user)->assertOk();

        $this->actingAs($user, 'sanctum')->getJson(self::GATED_ROUTE)->assertOk();
    }

    public function test_sync_requires_authentication(): void
    {
        $this->postJson('/api/subscription/sync')->assertUnauthorized();
    }

    public function test_a_webhook_and_a_sync_describing_the_same_purchase_leave_the_same_row(): void
    {
        $purchasedAt = now()->subDays(3);
        $expiresAt = now()->addDays(4);
        $viaWebhook = User::factory()->create();
        $viaSync = User::factory()->create();

        $this->postWebhook([
            'type' => 'INITIAL_PURCHASE',
            'app_user_id' => (string) $viaWebhook->id,
            'period_type' => 'TRIAL',
            'purchased_at_ms' => $purchasedAt->getTimestampMs(),
            'expiration_at_ms' => $expiresAt->getTimestampMs(),
        ])->assertOk();

        $this->fakeRevenueCat($this->subscriber([
            'period_type' => 'trial',
            'original_purchase_date' => $purchasedAt->toIso8601ZuluString(),
            'purchase_date' => $purchasedAt->toIso8601ZuluString(),
            'expires_date' => $expiresAt->toIso8601ZuluString(),
        ]));
        $this->sync($viaSync)->assertOk();

        // The REST API reports no USD price, so the sync leaves price unknown.
        $this->assertSame(
            $this->rowOf($viaWebhook, except: ['price', 'currency']),
            $this->rowOf($viaSync, except: ['price', 'currency']),
        );

        // And syncing the webhook's user changes nothing, price included.
        $before = $this->rowOf($viaWebhook);
        $this->sync($viaWebhook)->assertOk();
        $this->assertSame($before, $this->rowOf($viaWebhook));
    }

    public function test_an_older_webhook_processed_after_a_sync_does_not_undo_it(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->expired()->create(['user_id' => $user->id]);
        $this->fakeRevenueCat($this->subscriber());

        $this->sync($user)->assertOk()->assertJsonPath('user.subscription.status', 'active');

        $this->postWebhook([
            'type' => 'EXPIRATION',
            'app_user_id' => (string) $user->id,
            'event_timestamp_ms' => now()->subMinute()->getTimestampMs(),
        ])->assertOk();

        $this->actingAs($user, 'sanctum')->getJson('/api/user')
            ->assertJsonPath('user.subscription.status', 'active')
            ->assertJsonPath('user.entitlements', ['app_access']);
    }

    public function test_a_sandbox_subscription_is_ignored_in_production(): void
    {
        $user = User::factory()->create();
        $this->fakeRevenueCat($this->subscriber(['is_sandbox' => true]));
        $this->app['env'] = 'production';

        $response = $this->sync($user);

        $this->app['env'] = 'testing';
        $response->assertOk()->assertJsonPath('user.subscription.status', null);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_a_sandbox_subscription_is_recorded_outside_production(): void
    {
        $user = User::factory()->create();
        $this->fakeRevenueCat($this->subscriber(['is_sandbox' => true]));

        $this->sync($user)->assertOk()->assertJsonPath('user.subscription.status', 'active');
        $this->assertSame('sandbox', Subscription::where('user_id', $user->id)->value('environment'));
    }

    public function test_without_the_secret_key_it_answers_a_server_error_logs_and_leaves_the_row(): void
    {
        config(['services.revenuecat.secret_api_key' => null]);
        Log::spy();
        Http::fake();
        $user = User::factory()->create();
        Subscription::factory()->expired()->create(['user_id' => $user->id]);
        $before = $this->rowOf($user);

        $this->sync($user)
            ->assertStatus(500)
            ->assertJsonPath('code', 'subscription_sync_not_configured');

        $this->assertSame($before, $this->rowOf($user));
        Http::assertNothingSent();
        Log::shouldHaveReceived('error')->once();
    }

    public function test_a_revenuecat_error_answers_bad_gateway_and_leaves_the_row(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->expired()->create(['user_id' => $user->id]);
        $before = $this->rowOf($user);
        $this->fakeRevenueCat(['message' => 'Internal error'], 500);

        $this->sync($user)
            ->assertStatus(502)
            ->assertJsonPath('code', 'subscription_sync_failed');

        $this->assertSame($before, $this->rowOf($user));
    }

    public function test_a_subscriber_without_app_access_leaves_the_row(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->expired()->create(['user_id' => $user->id]);
        $before = $this->rowOf($user);
        $body = $this->subscriber();
        $body['subscriber']['entitlements'] = [];
        $this->fakeRevenueCat($body);

        $this->sync($user)->assertOk()->assertJsonPath('user.entitlements', []);

        $this->assertSame($before, $this->rowOf($user));
    }

    public function test_sync_is_throttled_per_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->fakeRevenueCat($this->subscriber());

        for ($i = 0; $i < 10; $i++) {
            $this->sync($user)->assertOk();
        }

        $this->sync($user)->assertTooManyRequests();
        $this->sync($other)->assertOk();
    }

    public function test_auto_renew_off_is_cancelled_with_access_until_expiry(): void
    {
        $user = User::factory()->create();
        $this->fakeRevenueCat($this->subscriber([
            'unsubscribe_detected_at' => now()->subDay()->toIso8601ZuluString(),
        ]));

        $this->sync($user)->assertOk()
            ->assertJsonPath('user.subscription.status', 'cancelled')
            ->assertJsonPath('user.entitlements', ['app_access']);
    }

    public function test_a_lapsed_subscription_is_expired_without_access(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id]);
        $this->fakeRevenueCat($this->subscriber([
            'expires_date' => now()->subDay()->toIso8601ZuluString(),
        ]));

        $this->sync($user)->assertOk()
            ->assertJsonPath('user.subscription.status', 'expired')
            ->assertJsonPath('user.entitlements', []);
    }

    public function test_a_billing_issue_keeps_access_through_the_store_grace_period(): void
    {
        $user = User::factory()->create();
        $this->fakeRevenueCat($this->subscriber([
            'expires_date' => now()->subDay()->toIso8601ZuluString(),
            'billing_issues_detected_at' => now()->subDay()->toIso8601ZuluString(),
            'grace_period_expires_date' => now()->addDays(5)->toIso8601ZuluString(),
        ]));

        $this->sync($user)->assertOk()
            ->assertJsonPath('user.subscription.status', 'billing_issue')
            ->assertJsonPath('user.entitlements', ['app_access']);

        $this->travel(6)->days();
        $this->actingAs($user, 'sanctum')->getJson('/api/user')->assertJsonPath('user.entitlements', []);
    }

    public function test_a_paused_play_subscription_syncs_as_paused_like_the_webhook(): void
    {
        $purchasedAt = now()->subDays(20);
        $expiresAt = now()->addDays(10);
        $viaWebhook = User::factory()->create();
        $viaSync = User::factory()->create();

        $this->postWebhook([
            'type' => 'INITIAL_PURCHASE',
            'app_user_id' => (string) $viaWebhook->id,
            'store' => 'PLAY_STORE',
            'purchased_at_ms' => $purchasedAt->getTimestampMs(),
            'expiration_at_ms' => $expiresAt->getTimestampMs(),
            'event_timestamp_ms' => now()->subMinutes(2)->getTimestampMs(),
        ])->assertOk();
        $this->postWebhook([
            'type' => 'SUBSCRIPTION_PAUSED',
            'app_user_id' => (string) $viaWebhook->id,
            'store' => 'PLAY_STORE',
            'auto_resume_at_ms' => now()->addMonths(2)->getTimestampMs(),
            'event_timestamp_ms' => now()->subMinute()->getTimestampMs(),
        ])->assertOk();

        // REST v1 marks a paused Google Play subscription by auto_resume_date.
        $this->fakeRevenueCat($this->subscriber([
            'store' => 'play_store',
            'original_purchase_date' => $purchasedAt->toIso8601ZuluString(),
            'purchase_date' => $purchasedAt->toIso8601ZuluString(),
            'expires_date' => $expiresAt->toIso8601ZuluString(),
            'auto_resume_date' => now()->addMonths(2)->toIso8601ZuluString(),
        ]));
        $this->sync($viaSync)->assertOk()
            ->assertJsonPath('user.subscription.status', 'paused')
            ->assertJsonPath('user.entitlements', ['app_access']);

        $this->assertSame(
            $this->rowOf($viaWebhook, except: ['price', 'currency']),
            $this->rowOf($viaSync, except: ['price', 'currency']),
        );
    }

    public function test_a_paused_subscription_past_its_paid_period_is_expired_without_access(): void
    {
        $user = User::factory()->create();
        $this->fakeRevenueCat($this->subscriber([
            'store' => 'play_store',
            'expires_date' => now()->subDay()->toIso8601ZuluString(),
            'auto_resume_date' => now()->addMonth()->toIso8601ZuluString(),
        ]));

        $this->sync($user)->assertOk()
            ->assertJsonPath('user.subscription.status', 'expired')
            ->assertJsonPath('user.entitlements', []);
    }

    public function test_a_paused_subscription_with_auto_renew_off_is_cancelled(): void
    {
        $user = User::factory()->create();
        $this->fakeRevenueCat($this->subscriber([
            'store' => 'play_store',
            'unsubscribe_detected_at' => now()->subDay()->toIso8601ZuluString(),
            'auto_resume_date' => now()->addMonths(2)->toIso8601ZuluString(),
        ]));

        $this->sync($user)->assertOk()
            ->assertJsonPath('user.subscription.status', 'cancelled')
            ->assertJsonPath('user.entitlements', ['app_access']);
    }

    public function test_a_refund_ends_access(): void
    {
        $user = User::factory()->create();
        $this->fakeRevenueCat($this->subscriber([
            'refunded_at' => now()->subHour()->toIso8601ZuluString(),
        ]));

        $this->sync($user)->assertOk()
            ->assertJsonPath('user.subscription.status', 'expired')
            ->assertJsonPath('user.entitlements', []);
    }

    public function test_a_late_purchase_webhook_after_a_sync_still_records_the_price(): void
    {
        $user = User::factory()->create();
        $this->fakeRevenueCat($this->subscriber([
            'unsubscribe_detected_at' => now()->toIso8601ZuluString(),
        ]));
        $this->sync($user)->assertOk();

        $this->postWebhook([
            'type' => 'INITIAL_PURCHASE',
            'app_user_id' => (string) $user->id,
            'price' => 4.99,
            'currency' => 'EUR',
            'event_timestamp_ms' => now()->subMinute()->getTimestampMs(),
        ])->assertOk();

        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertSame('4.99', $subscription->price);
        $this->assertSame('EUR', $subscription->currency);
        // Stale otherwise: the cancellation the sync saw stands.
        $this->assertSame('cancelled', $subscription->status->value);
    }
}
