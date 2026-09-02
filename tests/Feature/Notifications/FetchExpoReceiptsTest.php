<?php

namespace Tests\Feature\Notifications;

use App\Jobs\FetchExpoReceipts;
use App\Models\Device;
use App\Models\User;
use App\Notifications\TestPush;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Expo tells you whether a push was delivered only if you ask, some minutes
 * later, with the ticket ids it gave you. This job asks, and acts on the one
 * answer that matters: a Push Token reported dead ends its Device.
 */
class FetchExpoReceiptsTest extends TestCase
{
    use RefreshDatabase;

    private const RECEIPTS_URL = 'https://exp.host/--/api/v2/push/getReceipts';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('notifications.expo.access_token', 'expo-secret');
        $this->travelTo('2026-09-02 12:00:00');
    }

    /**
     * @param  array<int, string>  $tickets  device id => ticket id
     */
    private function sentNotification(User $user, array $tickets, string $ago): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => TestPush::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['title' => 'Hello', 'body' => 'Earlier', 'expo_tickets' => $tickets],
            'read_at' => null,
            'created_at' => now()->sub($ago),
            'updated_at' => now()->sub($ago),
        ]);
    }

    public function test_a_dead_receipt_ends_the_device_and_the_tickets_are_cleared(): void
    {
        Http::fake([self::RECEIPTS_URL => Http::response(['data' => [
            'ticket-alive' => ['status' => 'ok'],
            'ticket-dead' => ['status' => 'error', 'message' => 'gone', 'details' => ['error' => 'DeviceNotRegistered']],
        ]])]);

        $user = User::factory()->create();
        $alive = Device::factory()->for($user)->create();
        $dead = Device::factory()->for($user)->create();
        $row = $this->sentNotification($user, [$alive->id => 'ticket-alive', $dead->id => 'ticket-dead'], '30 minutes');

        (new FetchExpoReceipts)->handle();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === self::RECEIPTS_URL
            && $request->hasHeader('Authorization', 'Bearer expo-secret')
            && $request->data() === ['ids' => ['ticket-alive', 'ticket-dead']]);

        $this->assertDatabaseHas('devices', ['id' => $alive->id]);
        $this->assertDatabaseMissing('devices', ['id' => $dead->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $dead->personal_access_token_id]);

        $data = $row->fresh()->data;
        $this->assertArrayNotHasKey('expo_tickets', $data);
        $this->assertSame('Hello', $data['title']);
    }

    public function test_only_notifications_between_fifteen_and_ninety_minutes_old_are_asked_about(): void
    {
        Http::fake([self::RECEIPTS_URL => Http::response(['data' => ['ticket-mid' => ['status' => 'ok']]])]);

        $user = User::factory()->create();
        $device = Device::factory()->for($user)->create();
        $tooFresh = $this->sentNotification($user, [$device->id => 'ticket-fresh'], '5 minutes');
        $due = $this->sentNotification($user, [$device->id => 'ticket-mid'], '40 minutes');
        $tooOld = $this->sentNotification($user, [$device->id => 'ticket-old'], '2 hours');

        (new FetchExpoReceipts)->handle();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->data() === ['ids' => ['ticket-mid']]);
        $this->assertArrayNotHasKey('expo_tickets', $due->fresh()->data);
        $this->assertArrayHasKey('expo_tickets', $tooFresh->fresh()->data);
        $this->assertArrayHasKey('expo_tickets', $tooOld->fresh()->data);
    }

    public function test_nothing_due_means_no_request(): void
    {
        Http::fake();

        (new FetchExpoReceipts)->handle();

        Http::assertNothingSent();
    }

    public function test_other_receipt_errors_are_logged_and_keep_the_device(): void
    {
        Http::fake([self::RECEIPTS_URL => Http::response(['data' => [
            'ticket-1' => ['status' => 'error', 'message' => 'bad creds', 'details' => ['error' => 'InvalidCredentials']],
        ]])]);
        Log::shouldReceive('warning')->once();

        $user = User::factory()->create();
        $device = Device::factory()->for($user)->create();
        $this->sentNotification($user, [$device->id => 'ticket-1'], '20 minutes');

        (new FetchExpoReceipts)->handle();

        $this->assertDatabaseHas('devices', ['id' => $device->id]);
    }

    public function test_the_job_runs_every_fifteen_minutes(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->description ?? '', FetchExpoReceipts::class)
                || str_contains($event->command ?? '', FetchExpoReceipts::class));

        $this->assertCount(1, $events, 'FetchExpoReceipts should be scheduled exactly once');
        $this->assertSame('*/15 * * * *', $events->first()->expression);
    }

    public function test_a_ticket_expo_has_no_receipt_for_yet_stays_for_the_next_run(): void
    {
        Http::fake([self::RECEIPTS_URL => Http::response(['data' => ['ticket-answered' => ['status' => 'ok']]])]);

        $user = User::factory()->create();
        $answered = Device::factory()->for($user)->create();
        $pending = Device::factory()->for($user)->create();
        $row = $this->sentNotification($user, [$answered->id => 'ticket-answered', $pending->id => 'ticket-pending'], '20 minutes');

        (new FetchExpoReceipts)->handle();

        $this->assertSame([$pending->id => 'ticket-pending'], $row->fresh()->data['expo_tickets']);
    }
}
