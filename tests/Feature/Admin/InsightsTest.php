<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use App\Models\WorkoutTemplate;
use App\Services\Admin\Insights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The Insights module (spec 024, Seam 1): one structure per range, from known
 * fixtures. "Now" is Wednesday 7 Oct 2026, 12:00.
 */
class InsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 12:00:00');
    }

    public function test_retention_counts_completed_sessions_per_week_after_signup(): void
    {
        // Signed up 60 days ago: old enough for weeks 1, 2, 4 and 8.
        $ada = $this->member('2026-08-08 12:00:00');
        $this->workoutSession($ada, WorkoutSessionStatus::Completed, '2026-08-10 12:00:00'); // day 2 → week 1
        $this->workoutSession($ada, WorkoutSessionStatus::Completed, '2026-09-01 12:00:00'); // day 24 → week 4
        // Not completed: never counts (would be weeks 2 and 8).
        $this->workoutSession($ada, WorkoutSessionStatus::Cancelled, '2026-08-17 12:00:00');
        $this->workoutSession($ada, WorkoutSessionStatus::Active, '2026-09-28 12:00:00');

        // Signed up 50 days ago: reached weeks 1–4, not week 8. Trained in week 2 only.
        $grace = $this->member('2026-08-18 12:00:00');
        $this->workoutSession($grace, WorkoutSessionStatus::Completed, '2026-08-26 12:00:00'); // day 8 → week 2

        // Signed up 10 days ago: reached week 1 only. Trained in week 1.
        $linus = $this->member('2026-09-27 12:00:00');
        $this->workoutSession($linus, WorkoutSessionStatus::Completed, '2026-09-28 12:00:00');

        $retention = Insights::summary(90)['retention'];

        $this->assertSame(3, $retention['signups']);
        $this->assertSame([
            1 => ['share' => 67, 'retained' => 2, 'eligible' => 3, 'reached' => true],
            2 => ['share' => 50, 'retained' => 1, 'eligible' => 2, 'reached' => true],
            4 => ['share' => 50, 'retained' => 1, 'eligible' => 2, 'reached' => true],
            8 => ['share' => 0, 'retained' => 0, 'eligible' => 1, 'reached' => true],
        ], $retention['weeks']);
    }

    public function test_the_range_decides_which_signups_count_and_staff_never_do(): void
    {
        $recent = $this->member('2026-10-04 12:00:00');
        $this->workoutSession($recent, WorkoutSessionStatus::Completed, '2026-10-04 14:00:00');
        $older = $this->member('2026-08-01 12:00:00');
        $this->workoutSession($older, WorkoutSessionStatus::Completed, '2026-08-03 12:00:00');
        $admin = $this->userWithRole('admin', ['created_at' => '2026-10-05 12:00:00']);
        $this->workoutSession($admin, WorkoutSessionStatus::Completed, '2026-10-05 12:30:00');
        $this->userWithRole('partner_admin', ['created_at' => '2026-08-02 12:00:00']);

        $week = Insights::summary(7);
        $quarter = Insights::summary(90);

        $this->assertSame(1, $week['retention']['signups']);
        $this->assertFalse($week['retention']['weeks'][4]['reached']);
        $this->assertSame(['signups' => 1, 'median_hours' => 2.0], array_intersect_key($week['first_workout'], ['signups' => 0, 'median_hours' => 0]));

        $this->assertSame(2, $quarter['retention']['signups']);
        $this->assertSame(['share' => 100, 'retained' => 1, 'eligible' => 1, 'reached' => true], $quarter['retention']['weeks'][1]);
        $this->assertSame(['signups' => 2, 'median_hours' => 25.0], array_intersect_key($quarter['first_workout'], ['signups' => 0, 'median_hours' => 0]));
    }

    public function test_time_to_first_workout_is_a_median_against_the_range_before_with_buckets(): void
    {
        // Range of 30 days: 7 Sep 12:00 → now. Hours to the first Completed Session: 0.5, 12, 48, 120, 240.
        $ada = $this->member('2026-10-01 12:00:00');
        $this->workoutSession($ada, WorkoutSessionStatus::Completed, '2026-10-01 12:30:00');
        $this->workoutSession($ada, WorkoutSessionStatus::Completed, '2026-10-03 12:00:00');
        $grace = $this->member('2026-09-20 12:00:00');
        $this->workoutSession($grace, WorkoutSessionStatus::Cancelled, '2026-09-20 13:00:00');
        $this->workoutSession($grace, WorkoutSessionStatus::Completed, '2026-09-21 00:00:00');
        $this->workoutSession($this->member('2026-09-10 12:00:00'), WorkoutSessionStatus::Completed, '2026-09-12 12:00:00');
        $this->workoutSession($this->member('2026-09-15 12:00:00'), WorkoutSessionStatus::Completed, '2026-09-20 12:00:00');
        $this->workoutSession($this->member('2026-09-08 12:00:00'), WorkoutSessionStatus::Completed, '2026-09-18 12:00:00');
        // Not yet: no Completed Session, only an active one.
        $this->member('2026-10-05 12:00:00');
        $this->workoutSession($this->member('2026-10-06 12:00:00'), WorkoutSessionStatus::Active, '2026-10-06 13:00:00');

        // The range before (8 Aug → 7 Sep): 6 h and 48 h, plus one who never started.
        $this->workoutSession($this->member('2026-08-10 12:00:00'), WorkoutSessionStatus::Completed, '2026-08-10 18:00:00');
        $this->workoutSession($this->member('2026-08-20 12:00:00'), WorkoutSessionStatus::Completed, '2026-08-22 12:00:00');
        $this->member('2026-08-25 12:00:00');

        // Staff never count.
        $admin = $this->userWithRole('admin', ['created_at' => '2026-09-25 12:00:00']);
        $this->workoutSession($admin, WorkoutSessionStatus::Completed, '2026-09-25 12:10:00');
        $this->userWithRole('partner_admin', ['created_at' => '2026-09-26 12:00:00']);

        $firstWorkout = Insights::summary(30)['first_workout'];

        $this->assertSame([
            'signups' => 7,
            'median_hours' => 48.0,
            'previous_median_hours' => 27.0,
            'buckets' => [
                ['label' => 'Under 1 h', 'users' => 1],
                ['label' => '1–24 h', 'users' => 1],
                ['label' => '1–3 days', 'users' => 1],
                ['label' => '3–7 days', 'users' => 1],
                ['label' => 'Over 7 days', 'users' => 1],
                ['label' => 'Not yet', 'users' => 2],
            ],
        ], $firstWorkout);
    }

    public function test_generator_quality_splits_generated_from_other_sessions_and_swaps_from_cancels(): void
    {
        $ada = $this->member('2026-08-01 12:00:00');
        $created = fn (string $at, bool $generated, WorkoutSessionStatus $status, ?WorkoutSession $replaces = null) => tap(
            $this->workoutSession($ada, $status, $at),
            fn (WorkoutSession $session) => $session->forceFill([
                'created_at' => $at,
                'is_auto_generated' => $generated,
                'replaced_session_id' => $replaces?->id,
            ])->save(),
        );

        // Generated, created in the last 30 days: a draft regenerated into a
        // new one (swapped), the replacement completed, one plain cancel, one
        // completed, one still a draft (total only).
        $regenerated = $created('2026-09-20 12:00:00', true, WorkoutSessionStatus::Cancelled);
        $created('2026-09-20 12:05:00', true, WorkoutSessionStatus::Completed, $regenerated);
        $created('2026-09-25 12:00:00', true, WorkoutSessionStatus::Cancelled);
        $created('2026-09-28 12:00:00', true, WorkoutSessionStatus::Completed);
        $created('2026-10-05 12:00:00', true, WorkoutSessionStatus::Draft);

        // Other sessions: three completed, one active (total only).
        $created('2026-09-10 12:00:00', false, WorkoutSessionStatus::Completed);
        $created('2026-09-12 12:00:00', false, WorkoutSessionStatus::Completed);
        $created('2026-09-14 12:00:00', false, WorkoutSessionStatus::Completed);
        $created('2026-10-06 12:00:00', false, WorkoutSessionStatus::Active);

        // Outside the range: created 40 days ago.
        $created('2026-08-28 12:00:00', true, WorkoutSessionStatus::Cancelled);

        // Staff sessions never count.
        $admin = $this->userWithRole('admin');
        WorkoutSession::factory()->create([
            'user_id' => $admin->id,
            'workout_template_id' => WorkoutTemplate::factory()->state(['plan_id' => Plan::factory()->state(['user_id' => $admin->id])]),
            'status' => WorkoutSessionStatus::Cancelled,
            'is_auto_generated' => true,
            'created_at' => '2026-10-01 12:00:00',
        ]);

        $this->assertSame([
            'generated' => ['sessions' => 5, 'completed' => 40, 'swapped' => 20, 'cancelled' => 20],
            'other' => ['sessions' => 4, 'completed' => 75, 'swapped' => 0, 'cancelled' => 0],
        ], Insights::summary(30)['generator']);
    }

    public function test_skipped_exercises_rank_by_share_of_inclusions_with_no_set_log(): void
    {
        $ada = $this->member('2026-08-01 12:00:00');
        $squat = Exercise::factory()->create(['name' => 'Back Squat']);
        $lunge = Exercise::factory()->create(['name' => 'Walking Lunge']);
        $curl = Exercise::factory()->create(['name' => 'Cable Curl']);

        // Ten Completed Sessions in the last 30 days, each with ten Back
        // Squat rows (30 logged) and ten Walking Lunge rows (60 logged): 100
        // inclusions each. Cable Curl is in only 99 rows, so it never ranks.
        foreach (range(1, 10) as $i) {
            $session = $this->workoutSession($ada, WorkoutSessionStatus::Completed, sprintf('2026-09-%02d 18:00:00', $i));
            $session->forceFill(['completed_at' => '2026-09-'.(10 + $i).' 18:00:00'])->save();
            foreach (range(1, 10) as $row) {
                $this->included($session, $squat, logged: $row <= 3);
                $this->included($session, $lunge, logged: $row <= 6);
                if ($i < 10 || $row < 10) {
                    $this->included($session, $curl, logged: false);
                }
            }
        }

        // A set from before sets pointed at their row (no row id) still counts
        // as logged for the sole row of its exercise.
        $legacy = $this->workoutSession($ada, WorkoutSessionStatus::Completed, '2026-09-29 12:00:00');
        $this->included($legacy, $lunge, logged: true, legacy: true);

        // A cancelled session: its unlogged rows are not skips.
        $cancelled = $this->workoutSession($ada, WorkoutSessionStatus::Cancelled, '2026-09-30 12:00:00');
        foreach (range(1, 50) as $row) {
            $this->included($cancelled, $squat, logged: false);
        }

        // A Completed Session outside the range does not count either.
        $old = $this->workoutSession($ada, WorkoutSessionStatus::Completed, '2026-08-20 12:00:00');
        $this->included($old, $lunge, logged: false);

        $this->assertSame([
            ['exercise_id' => $squat->id, 'name' => 'Back Squat', 'included' => 100, 'skipped' => 70, 'rate' => 70],
            ['exercise_id' => $lunge->id, 'name' => 'Walking Lunge', 'included' => 101, 'skipped' => 40, 'rate' => 40],
        ], Insights::summary(30)['skipped']);
    }

    private function member(string $signedUpAt): User
    {
        return User::factory()->create([
            'created_at' => $signedUpAt,
            'onboarding_completed_at' => $signedUpAt,
        ]);
    }

    private function workoutSession(User $user, WorkoutSessionStatus $status, string $at): WorkoutSession
    {
        return WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => WorkoutTemplate::factory()->state(['plan_id' => Plan::factory()->state(['user_id' => $user->id])]),
            'status' => $status,
            'performed_at' => Carbon::parse($at)->subHour(),
            'completed_at' => $status === WorkoutSessionStatus::Completed ? $at : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function included(WorkoutSession $session, Exercise $exercise, bool $logged, bool $legacy = false): void
    {
        $row = WorkoutSessionExercise::create([
            'workout_session_id' => $session->id,
            'exercise_id' => $exercise->id,
        ]);

        if ($logged) {
            SetLog::create([
                'workout_session_id' => $session->id,
                'workout_session_exercise_id' => $legacy ? null : $row->id,
                'exercise_id' => $exercise->id,
                'set_number' => 1,
                'weight' => 60,
                'reps' => 8,
            ]);
        }
    }

    private function userWithRole(string $slug, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id);

        return $user;
    }
}
