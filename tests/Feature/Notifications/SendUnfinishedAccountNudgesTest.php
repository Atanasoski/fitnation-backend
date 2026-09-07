<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Notifications\UnfinishedAccountNudge;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendUnfinishedAccountNudgesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        $this->travelTo('2026-09-05 08:00:00 UTC'); // 10:00 in Skopje
    }

    private function registered(string $at, array $attributes = []): User
    {
        return User::factory()->create(['created_at' => $at, 'onboarding_completed_at' => null, ...$attributes]);
    }

    private function sentSubjects(): array
    {
        return Mail::mailer()->getSymfonyTransport()->messages()
            ->map(fn ($sent) => $sent->getOriginalMessage()->getSubject())
            ->sort()
            ->values()
            ->all();
    }

    public function test_it_mails_exactly_the_users_who_are_due_with_their_step(): void
    {
        $oneDay = $this->registered('2026-09-04 09:30:00 UTC', ['email_verified_at' => null]);
        $threeDays = $this->registered('2026-09-02 09:30:00 UTC');
        $this->registered('2026-09-05 07:30:00 UTC'); // today: not due
        $this->registered('2026-09-04 09:30:00 UTC', ['onboarding_completed_at' => '2026-09-04 10:00:00']);

        $this->artisan('notifications:unfinished-accounts')
            ->expectsOutputToContain('2')
            ->assertSuccessful();

        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(UnfinishedAccountNudge::class, $oneDay->notifications()->sole()->type);
        $this->assertSame(['step' => 1, 'stuck_at' => 'unverified'], collect($oneDay->notifications()->sole()->data)->only('step', 'stuck_at')->all());
        $this->assertSame(['step' => 3, 'stuck_at' => 'not_onboarded'], collect($threeDays->notifications()->sole()->data)->only('step', 'stuck_at')->all());

        $this->assertSame(
            ['Confirm your email for '.config('app.name'), 'Your first plan is two minutes away'],
            $this->sentSubjects(),
        );
        Http::assertNothingSent();
    }

    public function test_running_again_in_the_same_hour_sends_nothing_more(): void
    {
        $this->registered('2026-09-04 09:30:00 UTC');

        $this->artisan('notifications:unfinished-accounts')->assertSuccessful();
        $this->travelTo('2026-09-05 08:45:00 UTC');
        $this->artisan('notifications:unfinished-accounts')->assertSuccessful();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertCount(1, $this->sentSubjects());
    }

    public function test_it_is_scheduled_every_fifteen_minutes(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'notifications:unfinished-accounts'));

        $this->assertCount(1, $events);
        $this->assertSame('*/15 * * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
        $this->assertTrue($events->first()->onOneServer);
    }
}
