<?php

namespace Tests\Feature;

use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\FitnessMetrics\CompletedSessions;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What counts as a completed session, pinned.
 *
 * The two halves of the old FitnessMetricsService disagreed: a user's own score
 * read `completed_at IS NOT NULL` while the percentile cohort they were ranked
 * against read `status = completed`. A session that is one and not the other
 * counted for one half and not the other, and nothing said so.
 *
 * The answer is `status = completed` — it is what the complete() endpoint
 * writes and what every other consumer in the codebase already reads. These
 * tests exist so the two cannot drift apart again.
 */
class FitnessMetricsCompletedSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_session_with_a_completed_at_but_a_non_completed_status_does_not_count(): void
    {
        $user = $this->userWithBodyWeight();

        $this->sessionFor($user, WorkoutSessionStatus::Active, completedAt: now()->subDays(5)->addHour());

        $data = $this->metricsFor($user);

        $this->assertSame(0, $data['strength_score']['current']);
        $this->assertSame(0, $data['strength_balance']['percentage']);
        $this->assertSame(0, $data['weekly_progress']['current_week_workouts']);
    }

    public function test_a_completed_session_counts_even_without_a_completed_at(): void
    {
        $user = $this->userWithBodyWeight();

        $this->sessionFor($user, WorkoutSessionStatus::Completed, completedAt: null);

        $data = $this->metricsFor($user);

        $this->assertGreaterThan(0, $data['strength_score']['current']);
        $this->assertSame(1, $data['weekly_progress']['current_week_workouts']);
        // Balance stays 0: one trained muscle group is coverage without
        // evenness, which is the opposite of balanced.
        $this->assertSame(0, $data['strength_balance']['percentage']);
    }

    /**
     * The point of the shared module: the query that reads a user's own set
     * logs is the same query that reads a cohort member's, so the two cannot
     * define "completed" differently.
     */
    public function test_own_set_logs_and_another_users_set_logs_apply_the_same_predicate(): void
    {
        $self = $this->userWithBodyWeight();
        $other = $this->userWithBodyWeight();

        foreach ([$self, $other] as $user) {
            $this->sessionFor($user, WorkoutSessionStatus::Active, completedAt: now()->subDays(5)->addHour());
            $this->sessionFor($user, WorkoutSessionStatus::Completed, completedAt: null);
        }

        $from = now()->subDays(30);

        $this->assertSame(
            CompletedSessions::setLogs($self->id, $from)->count(),
            CompletedSessions::setLogs($other->id, $from)->count(),
            'Identically trained users must yield identically many countable set logs.'
        );

        // One of the two sessions each — the completed one, not the active one.
        $this->assertSame(1, CompletedSessions::setLogs($self->id, $from)->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function metricsFor(User $user): array
    {
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/user/fitness-metrics');

        $response->assertOk();

        return $response->json('data');
    }

    private function userWithBodyWeight(): User
    {
        $user = User::factory()->create(['partner_id' => null]);
        $user->profile->update(['weight' => 80.0]);

        return $user;
    }

    private function sessionFor(User $user, WorkoutSessionStatus $status, ?Carbon $completedAt): void
    {
        $muscleGroup = MuscleGroup::firstOrCreate(['name' => 'Chest'], ['body_region' => 'upper']);
        $exercise = Exercise::firstOrCreate(
            ['name' => 'Bench Press'],
            ['description' => 'Bench press exercise']
        );
        $exercise->muscleGroups()->syncWithoutDetaching([$muscleGroup->id => ['is_primary' => true]]);

        // Inside the last full week, so weekly progress sees it too.
        $performedAt = Carbon::now()->subWeek()->startOfWeek()->addHours(10);

        $session = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'status' => $status,
            'performed_at' => $performedAt,
            'completed_at' => $completedAt,
        ]);

        SetLog::create([
            'workout_session_id' => $session->id,
            'exercise_id' => $exercise->id,
            'set_number' => 1,
            'weight' => 100.0,
            'reps' => 10,
            'rest_seconds' => 60,
        ]);
    }
}
