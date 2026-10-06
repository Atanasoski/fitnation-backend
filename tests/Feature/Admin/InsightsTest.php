<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\WorkoutSessionStatus;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkoutSession;
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
    private function userWithRole(string $slug, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id);

        return $user;
    }
}
