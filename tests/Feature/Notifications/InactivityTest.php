<?php

namespace Tests\Feature\Notifications;

use App\Enums\WorkoutSessionStatus;
use App\Models\Device;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Notifications\InactivityNudge;
use App\Services\Notifications\Inactivity;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Inactivity Nudge rule (CONTEXT.md): who is due a nudge, evaluated at one
 * instant. Instants are written in UTC and the zone is always stated, because
 * the app timezone is Europe/Skopje and a bare timestamp would be read in it.
 * Skopje is UTC+2 in September, so 18:00 there is 16:00 UTC.
 */
class InactivityTest extends TestCase
{
    use RefreshDatabase;

    private const SKOPJE_SIX_PM = '2026-09-04 16:00:00 UTC';

    private static function at(string $instant): CarbonImmutable
    {
        return CarbonImmutable::parse($instant);
    }

    private function athlete(?string $timezone = 'Europe/Skopje', array $attributes = []): User
    {
        $user = User::factory()->create(['onboarding_completed_at' => '2026-08-01 10:00:00', ...$attributes]);
        Device::factory()->for($user)->create(['timezone' => $timezone]);

        return $user;
    }

    private function completed(User $user, string $at): WorkoutSession
    {
        return WorkoutSession::factory()->for($user)->create([
            'status' => WorkoutSessionStatus::Completed,
            'performed_at' => $at,
            'completed_at' => $at,
        ]);
    }

    public function test_three_days_of_silence_at_six_pm_local_is_due_at_step_three(): void
    {
        $user = $this->athlete();
        $this->completed($user, '2026-09-01 19:00:00');

        $due = Inactivity::dueAt(self::at(self::SKOPJE_SIX_PM));

        $this->assertCount(1, $due);
        $this->assertTrue($due->first()->user->is($user));
        $this->assertSame(3, $due->first()->step);
    }

    /** Record that a rung was sent at a given instant, as the command would have. */
    private function nudged(User $user, int $step, string $at): void
    {
        $this->travelTo(self::at($at));
        $user->notify(new InactivityNudge($step));
        $this->travelBack();
    }

    /** @return list<int> steps due, in user order */
    private function stepsDueAt(string $instant): array
    {
        return Inactivity::dueAt(self::at($instant))->map(fn ($c) => $c->step)->all();
    }

    public function test_two_days_of_silence_is_not_yet_due(): void
    {
        $user = $this->athlete();
        $this->completed($user, '2026-09-02 19:00:00');

        $this->assertSame([], $this->stepsDueAt(self::SKOPJE_SIX_PM));
    }

    public function test_six_pm_is_judged_in_each_users_own_timezone(): void
    {
        $skopje = $this->athlete('Europe/Skopje');
        $utc = $this->athlete('UTC');
        $this->completed($skopje, '2026-09-01 19:00:00');
        $this->completed($utc, '2026-09-01 19:00:00');

        $dueAt16 = Inactivity::dueAt(self::at('2026-09-04 16:00:00 UTC'));
        $this->assertCount(1, $dueAt16);
        $this->assertTrue($dueAt16->first()->user->is($skopje));

        $dueAt18 = Inactivity::dueAt(self::at('2026-09-04 18:00:00 UTC'));
        $this->assertCount(1, $dueAt18);
        $this->assertTrue($dueAt18->first()->user->is($utc));
    }

    public function test_every_run_within_the_hour_finds_the_same_user_due_until_they_are_nudged(): void
    {
        $user = $this->athlete();
        $this->completed($user, '2026-09-01 19:00:00');

        $this->assertSame([3], $this->stepsDueAt('2026-09-04 16:00:00 UTC'));
        $this->assertSame([3], $this->stepsDueAt('2026-09-04 16:45:00 UTC'));
        $this->assertSame([], $this->stepsDueAt('2026-09-04 17:00:00 UTC'));

        $this->nudged($user, 3, '2026-09-04 16:02:00 UTC');
        $this->assertSame([], $this->stepsDueAt('2026-09-04 16:45:00 UTC'));
    }

    public function test_a_device_with_no_timezone_is_read_in_the_home_timezone(): void
    {
        $user = $this->athlete(timezone: null);
        $this->completed($user, '2026-09-01 19:00:00');

        $this->assertSame([3], $this->stepsDueAt(self::SKOPJE_SIX_PM));
        $this->assertSame([], $this->stepsDueAt('2026-09-04 18:00:00 UTC'));
    }

    public function test_with_two_devices_the_most_recently_seen_one_sets_the_timezone(): void
    {
        $user = $this->athlete('Europe/Skopje');
        $user->devices()->update(['last_seen_at' => '2026-08-20 10:00:00']);
        Device::factory()->for($user)->create(['timezone' => 'America/New_York', 'last_seen_at' => '2026-09-03 10:00:00']);
        $this->completed($user, '2026-09-01 19:00:00');

        $this->assertSame([], $this->stepsDueAt(self::SKOPJE_SIX_PM));
        $this->assertSame([3], $this->stepsDueAt('2026-09-04 22:00:00 UTC')); // 18:00 in New York (UTC-4)
    }

    public function test_the_ladder_is_three_then_seven_then_fourteen_and_then_silence(): void
    {
        $user = $this->athlete();
        $this->completed($user, '2026-08-01 19:00:00');

        $this->assertSame([3], $this->stepsDueAt('2026-08-04 16:00:00 UTC'));
        $this->nudged($user, 3, '2026-08-04 16:00:00 UTC');

        foreach ([5, 6, 7] as $day) {
            $this->assertSame([], $this->stepsDueAt("2026-08-0{$day} 16:00:00 UTC"), 'day '.($day - 1));
        }

        $this->assertSame([7], $this->stepsDueAt('2026-08-08 16:00:00 UTC'));
        $this->nudged($user, 7, '2026-08-08 16:00:00 UTC');
        $this->assertSame([], $this->stepsDueAt('2026-08-12 16:00:00 UTC'));

        $this->assertSame([14], $this->stepsDueAt('2026-08-15 16:00:00 UTC'));
        $this->nudged($user, 14, '2026-08-15 16:00:00 UTC');

        $this->assertSame([], $this->stepsDueAt('2026-08-22 16:00:00 UTC'));
        $this->assertSame([], $this->stepsDueAt('2026-09-30 16:00:00 UTC'));
    }

    public function test_training_again_resets_the_ladder(): void
    {
        $user = $this->athlete();
        $this->completed($user, '2026-08-01 19:00:00');
        $this->nudged($user, 3, '2026-08-04 16:00:00 UTC');
        $this->nudged($user, 7, '2026-08-08 16:00:00 UTC');

        $this->completed($user, '2026-08-11 19:00:00');

        $this->assertSame([], $this->stepsDueAt('2026-08-13 16:00:00 UTC'));
        $this->assertSame([3], $this->stepsDueAt('2026-08-14 16:00:00 UTC'));
    }

    public function test_a_user_who_has_never_trained_is_measured_from_finishing_onboarding(): void
    {
        $user = $this->athlete(attributes: ['onboarding_completed_at' => '2026-09-01 12:00:00']);

        $this->assertSame([], $this->stepsDueAt('2026-09-03 16:00:00 UTC'));
        $this->assertSame([3], $this->stepsDueAt('2026-09-04 16:00:00 UTC'));
    }

    public function test_a_cancelled_session_is_not_training(): void
    {
        $user = $this->athlete();
        $this->completed($user, '2026-09-01 19:00:00');
        WorkoutSession::factory()->for($user)->create([
            'status' => WorkoutSessionStatus::Cancelled,
            'performed_at' => '2026-09-03 19:00:00',
            'completed_at' => null,
        ]);

        $this->assertSame([3], $this->stepsDueAt(self::SKOPJE_SIX_PM));
    }

    public function test_who_is_never_nudged(): void
    {
        $onboarding = $this->athlete(attributes: ['onboarding_completed_at' => null]);
        $this->completed($onboarding, '2026-09-01 19:00:00');

        $midWorkout = $this->athlete();
        $this->completed($midWorkout, '2026-09-01 19:00:00');
        WorkoutSession::factory()->for($midWorkout)->create([
            'status' => WorkoutSessionStatus::Active,
            'performed_at' => '2026-09-04 17:00:00',
            'completed_at' => null,
        ]);

        $previewing = $this->athlete();
        $this->completed($previewing, '2026-09-01 19:00:00');
        WorkoutSession::factory()->for($previewing)->create([
            'status' => WorkoutSessionStatus::Draft,
            'performed_at' => null,
            'completed_at' => null,
        ]);

        $switchedOff = $this->athlete(attributes: ['push_enabled' => false]);
        $this->completed($switchedOff, '2026-09-01 19:00:00');

        $noDevice = User::factory()->create(['onboarding_completed_at' => '2026-08-01 10:00:00']);
        $this->completed($noDevice, '2026-09-01 19:00:00');

        $control = $this->athlete();
        $this->completed($control, '2026-09-01 19:00:00');

        $due = Inactivity::dueAt(self::at(self::SKOPJE_SIX_PM));

        $this->assertCount(1, $due);
        $this->assertTrue($due->first()->user->is($control));
    }

    public function test_the_number_of_queries_does_not_grow_with_the_number_of_users(): void
    {
        $count = function (int $users): int {
            foreach (range(1, $users) as $i) {
                $this->completed($this->athlete(), '2026-09-01 19:00:00');
            }
            DB::flushQueryLog();
            DB::enableQueryLog();
            $due = Inactivity::dueAt(self::at(self::SKOPJE_SIX_PM));
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();
            // One Device per athlete; the session factory also creates plan owners, who have none.
            $this->assertCount(Device::count(), $due);

            return $queries;
        };

        $withTen = $count(10);
        $withThirtyMore = $count(30);

        $this->assertSame($withTen, $withThirtyMore);
        $this->assertLessThanOrEqual(6, $withTen);
    }
}
