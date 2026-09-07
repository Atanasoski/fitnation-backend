<?php

namespace Tests\Feature\Notifications;

use App\Enums\WorkoutSessionStatus;
use App\Models\Device;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Notifications\WeeklySummary;
use App\Services\Notifications\WeeklySummaries;
use App\Support\StoredClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The Weekly Summary rule (CONTEXT.md): who is due the email, evaluated at one
 * instant. Instants are written in UTC with the zone stated; fixtures written
 * in a user's clock go through StoredClock, as the app's own writes do. Skopje
 * is UTC+2 in September, so Monday 08:00 there is 06:00 UTC; New York is
 * UTC-4, so 08:00 there is 12:00 UTC.
 */
class WeeklySummariesTest extends TestCase
{
    use RefreshDatabase;

    /** Monday 2026-09-07, 08:00 in Skopje. */
    private const SKOPJE_MONDAY_EIGHT = '2026-09-07 06:00:00 UTC';

    private static function at(string $instant): CarbonImmutable
    {
        return CarbonImmutable::parse($instant);
    }

    /** A Completed Session at a local clock time. */
    private function trained(User $user, string $localClock, string $timezone = 'Europe/Skopje'): void
    {
        $performedAt = StoredClock::bind(CarbonImmutable::parse($localClock, $timezone));

        WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutSessionStatus::Completed,
            'performed_at' => $performedAt,
            'completed_at' => $performedAt->addHour(),
        ]);
    }

    /** Record that a summary was sent at a given instant, as the command would have. */
    private function summarised(User $user, string $instant): void
    {
        $at = StoredClock::bind(self::at($instant));

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => WeeklySummary::class,
            'data' => [],
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    /** @return list<int> user ids due, in user order */
    private function dueAt(string $instant): array
    {
        return WeeklySummaries::dueAt(self::at($instant))->map(fn ($c) => $c->user->id)->all();
    }

    public function test_a_user_who_trained_last_week_is_due_on_monday_at_eight_with_last_weeks_numbers(): void
    {
        $user = User::factory()->create();
        $this->trained($user, '2026-08-31 18:00');
        $this->trained($user, '2026-09-02 18:00');
        $this->trained($user, '2026-09-05 10:00');
        $this->trained($user, '2026-08-27 18:00'); // the week before: the comparison

        $due = WeeklySummaries::dueAt(self::at(self::SKOPJE_MONDAY_EIGHT));

        $this->assertCount(1, $due);
        $this->assertTrue($due->first()->user->is($user));
        $this->assertSame(3, $due->first()->progress()['current_week_workouts']);
        $this->assertSame(1, $due->first()->progress()['previous_week_workouts']);
        $this->assertSame('up', $due->first()->progress()['trend']);
    }

    public function test_only_the_eight_oclock_hour_on_monday_counts(): void
    {
        $user = User::factory()->create();
        $this->trained($user, '2026-09-02 18:00');

        $this->assertSame([$user->id], $this->dueAt('2026-09-07 06:00:00 UTC'), 'Monday 08:00');
        $this->assertSame([$user->id], $this->dueAt('2026-09-07 06:45:00 UTC'), 'Monday 08:45');
        $this->assertSame([], $this->dueAt('2026-09-07 05:45:00 UTC'), 'Monday 07:45');
        $this->assertSame([], $this->dueAt('2026-09-07 07:00:00 UTC'), 'Monday 09:00');
        $this->assertSame([], $this->dueAt('2026-09-08 06:00:00 UTC'), 'Tuesday 08:00');
        $this->assertSame([], $this->dueAt('2026-09-06 06:00:00 UTC'), 'Sunday 08:00');
    }

    public function test_training_only_in_the_week_before_last_is_still_due_with_a_zero_week(): void
    {
        $user = User::factory()->create();
        $this->trained($user, '2026-08-25 18:00');

        $due = WeeklySummaries::dueAt(self::at(self::SKOPJE_MONDAY_EIGHT));

        $this->assertCount(1, $due);
        $this->assertSame(0, $due->first()->progress()['current_week_workouts']);
        $this->assertSame(1, $due->first()->progress()['previous_week_workouts']);
    }

    public function test_no_training_in_either_week_is_not_due(): void
    {
        $user = User::factory()->create();
        $this->trained($user, '2026-08-20 18:00'); // three weeks back
        $this->trained($user, '2026-09-07 07:00'); // this morning: the week in progress
        WorkoutSession::factory()->create([ // last week, but never completed
            'user_id' => $user->id,
            'status' => WorkoutSessionStatus::Active,
            'performed_at' => CarbonImmutable::parse('2026-09-03 18:00', 'Europe/Skopje'),
            'completed_at' => null,
        ]);

        $this->assertSame([], $this->dueAt(self::SKOPJE_MONDAY_EIGHT));
    }

    public function test_the_email_preference_gates_it_and_the_push_switch_does_not(): void
    {
        $unsubscribed = User::factory()->create(['notification_settings' => ['weekly_summary_email' => false]]);
        $pushOff = User::factory()->create(['push_enabled' => false]);
        $resubscribed = User::factory()->create(['notification_settings' => ['weekly_summary_email' => true]]);

        foreach ([$unsubscribed, $pushOff, $resubscribed] as $user) {
            $this->trained($user, '2026-09-02 18:00');
        }

        $this->assertSame([$pushOff->id, $resubscribed->id], $this->dueAt(self::SKOPJE_MONDAY_EIGHT));
    }

    public function test_once_sent_this_monday_it_is_not_due_again_at_the_next_run(): void
    {
        $user = User::factory()->create();
        $this->trained($user, '2026-09-02 18:00');
        $this->summarised($user, '2026-08-31 06:02:00 UTC'); // last Monday's: irrelevant

        $this->assertSame([$user->id], $this->dueAt('2026-09-07 06:00:00 UTC'));

        $this->summarised($user, '2026-09-07 06:02:00 UTC');

        $this->assertSame([], $this->dueAt('2026-09-07 06:45:00 UTC'));
    }

    public function test_a_user_with_a_device_is_read_in_its_timezone(): void
    {
        $newYork = User::factory()->create();
        Device::factory()->for($newYork)->create(['timezone' => 'America/New_York']);
        $this->trained($newYork, '2026-09-02 18:00', 'America/New_York');

        $skopje = User::factory()->create();
        $this->trained($skopje, '2026-09-02 18:00');

        $this->assertSame([$skopje->id], $this->dueAt('2026-09-07 06:00:00 UTC'), '08:00 in Skopje, 02:00 in New York');
        $this->assertSame([$newYork->id], $this->dueAt('2026-09-07 12:00:00 UTC'), '08:00 in New York, 14:00 in Skopje');
    }

    public function test_the_week_ends_at_sunday_midnight_in_the_users_own_time(): void
    {
        $newYork = User::factory()->create();
        Device::factory()->for($newYork)->create(['timezone' => 'America/New_York']);
        // Sunday 23:30 in New York is Monday 05:30 in Skopje: last week, to the user.
        $this->trained($newYork, '2026-09-06 23:30', 'America/New_York');

        $due = WeeklySummaries::dueAt(self::at('2026-09-07 12:00:00 UTC'));

        $this->assertCount(1, $due);
        $this->assertSame(1, $due->first()->progress()['current_week_workouts']);
        $this->assertSame('America/New_York', $due->first()->asOf->getTimezone()->getName());
    }

    public function test_the_number_of_queries_does_not_grow_with_the_number_of_users(): void
    {
        $trained = 0;

        $count = function (int $users) use (&$trained): int {
            foreach (range(1, $users) as $i) {
                $this->trained(User::factory()->create(), '2026-09-02 18:00');
                $trained++;
            }
            DB::flushQueryLog();
            DB::enableQueryLog();
            $due = WeeklySummaries::dueAt(self::at(self::SKOPJE_MONDAY_EIGHT));
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();
            $this->assertCount($trained, $due);

            return $queries;
        };

        $withTen = $count(10);
        $withThirtyMore = $count(30);

        $this->assertSame($withTen, $withThirtyMore);
        $this->assertLessThanOrEqual(4, $withTen);
    }
}
