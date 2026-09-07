<?php

namespace Tests\Feature\Notifications;

use App\Enums\WorkoutSessionStatus;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Notifications\WeeklySummary;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendWeeklySummariesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-07 06:00:00 UTC'); // Monday 08:00 in Skopje
    }

    private function trained(User $user, string $skopjeClock): void
    {
        $performedAt = CarbonImmutable::parse($skopjeClock, 'Europe/Skopje')->setTimezone(config('app.timezone'));

        WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutSessionStatus::Completed,
            'performed_at' => $performedAt,
            'completed_at' => $performedAt->addHour(),
        ]);
    }

    /** @return list<string> */
    private function sentSubjects(): array
    {
        return Mail::mailer()->getSymfonyTransport()->messages()
            ->map(fn ($sent) => $sent->getOriginalMessage()->getSubject())
            ->sort()
            ->values()
            ->all();
    }

    public function test_it_mails_exactly_the_users_who_are_due_with_their_week(): void
    {
        $threeLastWeek = User::factory()->create();
        foreach (['2026-08-31 18:00', '2026-09-02 18:00', '2026-09-04 18:00'] as $clock) {
            $this->trained($threeLastWeek, $clock);
        }

        $onlyTheWeekBefore = User::factory()->create();
        $this->trained($onlyTheWeekBefore, '2026-08-26 18:00');

        $unsubscribed = User::factory()->create(['notification_settings' => ['weekly_summary_email' => false]]);
        $this->trained($unsubscribed, '2026-09-02 18:00');

        $idle = User::factory()->create();
        $this->trained($idle, '2026-08-10 18:00');

        $this->artisan('notifications:weekly-summaries')
            ->expectsOutputToContain('2')
            ->assertSuccessful();

        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(WeeklySummary::class, $threeLastWeek->notifications()->sole()->type);
        $this->assertSame(3, $threeLastWeek->notifications()->sole()->data['workouts']);
        $this->assertSame(0, $onlyTheWeekBefore->notifications()->sole()->data['workouts']);
        $this->assertSame(1, $onlyTheWeekBefore->notifications()->sole()->data['previous_workouts']);

        $this->assertSame(['Your week: 3 workouts', 'Your week: no workouts'], $this->sentSubjects());
    }

    public function test_running_again_in_the_same_hour_sends_nothing_more(): void
    {
        $this->trained(User::factory()->create(), '2026-09-02 18:00');

        $this->artisan('notifications:weekly-summaries')->assertSuccessful();
        $this->travelTo('2026-09-07 06:45:00 UTC');
        $this->artisan('notifications:weekly-summaries')->assertSuccessful();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertCount(1, $this->sentSubjects());
    }

    public function test_on_any_other_day_it_sends_nothing(): void
    {
        $this->trained(User::factory()->create(), '2026-09-02 18:00');

        $this->travelTo('2026-09-08 06:00:00 UTC'); // Tuesday
        $this->artisan('notifications:weekly-summaries')
            ->expectsOutputToContain('0')
            ->assertSuccessful();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_it_is_scheduled_every_fifteen_minutes(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'notifications:weekly-summaries'));

        $this->assertCount(1, $events);
        $this->assertSame('*/15 * * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
        $this->assertTrue($events->first()->onOneServer);
    }
}
