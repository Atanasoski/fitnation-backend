<?php

namespace Tests\Feature;

use App\Webhooks\RevenueCat\ProcessRevenueCatWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\WebhookClient\Models\WebhookCall;
use Tests\TestCase;

/**
 * Locks what `revenuecat:replay-failed` does, before its replay logic is
 * shared with the System page.
 */
class ReplayFailedRevenueCatWebhooksCommandTest extends TestCase
{
    use RefreshDatabase;

    private function failedCall(array $attributes = []): WebhookCall
    {
        return WebhookCall::create([
            'name' => 'revenuecat',
            'url' => '/api/webhooks/revenuecat',
            'payload' => ['event' => ['type' => 'INITIAL_PURCHASE']],
            'exception' => ['code' => 0, 'message' => 'User not found', 'trace' => ''],
            ...$attributes,
        ]);
    }

    public function test_it_replays_recent_failed_calls_and_clears_their_exception(): void
    {
        Queue::fake();
        $this->travelTo('2026-10-05 12:00:00');

        $recent = $this->failedCall();
        $old = $this->failedCall();
        $old->forceFill(['created_at' => now()->subDays(8)])->save();
        $succeeded = $this->failedCall(['exception' => null]);
        $otherWebhook = $this->failedCall(['name' => 'stripe']);

        $this->artisan('revenuecat:replay-failed')->assertSuccessful();

        $this->assertNull($recent->fresh()->exception);
        $this->assertNotNull($old->fresh()->exception);
        $this->assertNotNull($otherWebhook->fresh()->exception);
        Queue::assertPushed(ProcessRevenueCatWebhook::class, 1);
        Queue::assertPushed(ProcessRevenueCatWebhook::class, fn ($job) => $job->webhookCall->is($recent));
        $this->assertNull($succeeded->fresh()->exception);
    }

    public function test_all_replays_every_failed_call_regardless_of_age(): void
    {
        Queue::fake();
        $this->travelTo('2026-10-05 12:00:00');

        $this->failedCall();
        $old = $this->failedCall();
        $old->forceFill(['created_at' => now()->subDays(30)])->save();

        $this->artisan('revenuecat:replay-failed --all')->assertSuccessful();

        Queue::assertPushed(ProcessRevenueCatWebhook::class, 2);
        $this->assertNull($old->fresh()->exception);
    }

    public function test_id_replays_only_the_named_calls(): void
    {
        Queue::fake();

        $named = $this->failedCall();
        $other = $this->failedCall();

        $this->artisan('revenuecat:replay-failed', ['--id' => [$named->id]])->assertSuccessful();

        Queue::assertPushed(ProcessRevenueCatWebhook::class, 1);
        $this->assertNull($named->fresh()->exception);
        $this->assertNotNull($other->fresh()->exception);
    }

    public function test_it_reports_when_nothing_failed(): void
    {
        Queue::fake();

        $this->artisan('revenuecat:replay-failed')
            ->expectsOutput('No failed RevenueCat webhook calls to replay.')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }
}
