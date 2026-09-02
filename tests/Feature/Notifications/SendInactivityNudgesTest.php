<?php

namespace Tests\Feature\Notifications;

use App\Enums\WorkoutSessionStatus;
use App\Models\Device;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Notifications\InactivityNudge;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendInactivityNudgesTest extends TestCase
{
    use RefreshDatabase;

    private const SEND_URL = 'https://exp.host/--/api/v2/push/send';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('notifications.enabled', true);
        config()->set('notifications.expo.access_token', 'expo-secret');
        Http::fake([self::SEND_URL => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket']]])]);
        $this->travelTo('2026-09-04 16:00:00 UTC'); // 18:00 in Skopje
    }

    private function quietAthlete(string $lastTrained): User
    {
        $user = User::factory()->create(['onboarding_completed_at' => '2026-08-01 10:00:00']);
        Device::factory()->for($user)->create(['timezone' => 'Europe/Skopje']);
        WorkoutSession::factory()->for($user)->create([
            'status' => WorkoutSessionStatus::Completed,
            'performed_at' => $lastTrained,
            'completed_at' => $lastTrained,
        ]);

        return $user;
    }

    public function test_it_nudges_exactly_the_users_who_are_due_with_their_rung(): void
    {
        $threeDays = $this->quietAthlete('2026-09-01 19:00:00');
        $eightDays = $this->quietAthlete('2026-08-27 19:00:00');
        $this->quietAthlete('2026-09-03 19:00:00'); // one day: not due

        $this->artisan('notifications:inactivity')
            ->expectsOutputToContain('2')
            ->assertSuccessful();

        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(3, $threeDays->notifications()->sole()->data['step']);
        $this->assertSame(7, $eightDays->notifications()->sole()->data['step']);
        $this->assertSame(InactivityNudge::class, $threeDays->notifications()->sole()->type);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request->data()[0]['title'] === 'Ready when you are'
            && $request->data()[0]['data']['url'] === 'fitnation://dashboard');
        Http::assertSent(fn (Request $request) => $request->data()[0]['title'] === 'A week off');
    }

    public function test_running_again_in_the_same_hour_sends_nothing_more(): void
    {
        $this->quietAthlete('2026-09-01 19:00:00');

        $this->artisan('notifications:inactivity')->assertSuccessful();
        $this->travelTo('2026-09-04 16:45:00 UTC');
        $this->artisan('notifications:inactivity')->assertSuccessful();

        $this->assertDatabaseCount('notifications', 1);
        Http::assertSentCount(1);
    }

    public function test_it_is_scheduled_every_fifteen_minutes(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'notifications:inactivity'));

        $this->assertCount(1, $events);
        $this->assertSame('*/15 * * * *', $events->first()->expression);
    }
}
