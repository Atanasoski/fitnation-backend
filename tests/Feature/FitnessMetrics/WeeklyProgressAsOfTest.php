<?php

namespace Tests\Feature\FitnessMetrics;

use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\FitnessMetrics\WeeklyProgress;
use App\Support\StoredClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WeeklyProgress::for() read "as of" an instant other than now — the Weekly
 * Summary's local Monday. The rule that the week in progress is never the
 * subject is unchanged; only the clock it is read against moves.
 */
class WeeklyProgressAsOfTest extends TestCase
{
    use RefreshDatabase;

    /** A Completed Session at the given instant. */
    private function completedSession(User $user, CarbonImmutable $performedAt, float $weight = 100, int $reps = 10): void
    {
        $performedAt = StoredClock::bind($performedAt);

        $session = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutSessionStatus::Completed,
            'performed_at' => $performedAt,
            'completed_at' => $performedAt->addHour(),
        ]);

        SetLog::create([
            'workout_session_id' => $session->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'set_number' => 1,
            'weight' => $weight,
            'reps' => $reps,
            'rest_seconds' => 60,
        ]);
    }

    public function test_a_fixed_as_of_reads_the_same_payload_as_travelling_there(): void
    {
        $user = User::factory()->create();
        $asOf = CarbonImmutable::parse('2026-09-07 08:00:00', 'Europe/Skopje'); // a Monday

        $this->completedSession($user, $asOf->subWeek()->addDays(1)->setTime(18, 0));
        $this->completedSession($user, $asOf->subWeek()->addDays(3)->setTime(18, 0));
        $this->completedSession($user, $asOf->subWeeks(2)->addDays(2)->setTime(18, 0), 50, 10);
        $this->completedSession($user, $asOf->addHours(2)); // this week: never the subject

        $this->travelTo($asOf);
        $travelled = (new WeeklyProgress)->for($user);
        $this->travelBack();

        $fixed = (new WeeklyProgress)->for($user, $asOf);

        $this->assertSame($travelled, $fixed);
        $this->assertSame(2, $fixed['current_week_workouts']);
        $this->assertSame(1, $fixed['previous_week_workouts']);
        $this->assertSame(2000, $fixed['current_week_volume']);
        $this->assertSame(500, $fixed['previous_week_volume']);
    }

    public function test_the_week_is_bounded_in_the_as_of_timezone(): void
    {
        $user = User::factory()->create();
        $newYorkMonday = CarbonImmutable::parse('2026-09-07 08:00:00', 'America/New_York');

        // Sunday 23:30 in New York is already Monday 05:30 in Skopje. To the
        // user it belongs to the week just ended.
        $this->completedSession($user, CarbonImmutable::parse('2026-09-06 23:30:00', 'America/New_York'));
        // Monday 00:30 in New York a week earlier: the previous week, not the one before it.
        $this->completedSession($user, CarbonImmutable::parse('2026-08-31 00:30:00', 'America/New_York'), 50, 10);

        $progress = (new WeeklyProgress)->for($user, $newYorkMonday);

        $this->assertSame(2, $progress['current_week_workouts']);
        $this->assertSame(0, $progress['previous_week_workouts']);
        $this->assertSame(1500, $progress['current_week_volume']);
        $this->assertSame('2026-08-31', $progress['daily_breakdown'][0]['date']);
        $this->assertSame(1, $progress['daily_breakdown'][6]['workouts'], 'the Sunday-night session sits on Sunday, read in New York');
        $this->assertSame(1, $progress['daily_breakdown'][0]['workouts']);
    }
}
