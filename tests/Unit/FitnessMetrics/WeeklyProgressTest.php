<?php

namespace Tests\Unit\FitnessMetrics;

use App\Models\SetLog;
use App\Models\WorkoutSession;
use App\Services\FitnessMetrics\WeeklyProgress;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * The weekly-progress arithmetic, exercised without a database.
 *
 * The sessions here are unsaved models — enough to be summed and bucketed by
 * day, which is all these two functions do. Nothing touches the database, but
 * the framework has to be booted for Eloquent's casts to resolve.
 */
class WeeklyProgressTest extends TestCase
{
    public function test_growth_from_nothing_is_a_hundred_percent_not_an_infinity(): void
    {
        $this->assertSame(100.0, WeeklyProgress::percentageChange(3, 0));
    }

    public function test_no_change_either_way_is_zero(): void
    {
        $this->assertSame(0.0, WeeklyProgress::percentageChange(0, 0));
        $this->assertSame(0.0, WeeklyProgress::percentageChange(4, 4));
    }

    public function test_growth_and_decline_are_relative_to_the_earlier_week(): void
    {
        $this->assertSame(50.0, WeeklyProgress::percentageChange(3, 2));
        $this->assertSame(-50.0, WeeklyProgress::percentageChange(1, 2));
    }

    public function test_a_daily_breakdown_covers_all_seven_days_of_the_week(): void
    {
        $weekStart = Carbon::parse('2026-03-02'); // Monday

        $breakdown = WeeklyProgress::dailyBreakdown($weekStart, collect());

        $this->assertCount(7, $breakdown);
        $this->assertSame(range(0, 6), array_column($breakdown, 'day_of_week'));
        $this->assertSame('2026-03-02', $breakdown[0]['date']);
        $this->assertSame('2026-03-08', $breakdown[6]['date']);
        $this->assertSame([0, 0, 0, 0, 0, 0, 0], array_column($breakdown, 'volume'));
    }

    public function test_sessions_land_on_their_day_with_their_volume_and_duration(): void
    {
        $weekStart = Carbon::parse('2026-03-02'); // Monday

        $breakdown = WeeklyProgress::dailyBreakdown($weekStart, collect([
            self::workoutSession('2026-03-04 10:00', durationMinutes: 45, sets: [[100, 10], [100, 8]]),
            self::workoutSession('2026-03-04 18:00', durationMinutes: 30, sets: [[50, 10]]),
            self::workoutSession('2026-03-08 09:00', durationMinutes: 60, sets: [[60, 5]]),
        ]));

        // Wednesday: two sessions, 1000 + 800 + 500 kg, 75 minutes.
        $this->assertSame(2, $breakdown[2]['workouts']);
        $this->assertSame(2300, $breakdown[2]['volume']);
        $this->assertSame(75, $breakdown[2]['time_minutes']);

        // Sunday, the last day of the week, not the first.
        $this->assertSame(1, $breakdown[6]['workouts']);
        $this->assertSame(300, $breakdown[6]['volume']);

        $this->assertSame(0, $breakdown[0]['workouts']);
    }

    public function test_a_session_that_was_never_closed_out_contributes_no_time(): void
    {
        $session = self::workoutSession('2026-03-02 10:00', durationMinutes: null, sets: [[100, 10]]);

        $breakdown = WeeklyProgress::dailyBreakdown(Carbon::parse('2026-03-02'), collect([$session]));

        $this->assertSame(1, $breakdown[0]['workouts']);
        $this->assertSame(1000, $breakdown[0]['volume']);
        $this->assertSame(0, $breakdown[0]['time_minutes']);
    }

    /**
     * @param  array<int, array{0: float, 1: int}>  $sets  weight and reps
     */
    private static function workoutSession(string $performedAt, ?int $durationMinutes, array $sets): WorkoutSession
    {
        $performed = Carbon::parse($performedAt);

        $session = new WorkoutSession([
            'performed_at' => $performed,
            'completed_at' => $durationMinutes === null ? null : $performed->copy()->addMinutes($durationMinutes),
        ]);

        $session->setRelation('setLogs', collect(array_map(
            fn (array $set) => new SetLog(['weight' => $set[0], 'reps' => $set[1]]),
            $sets,
        )));

        return $session;
    }
}
