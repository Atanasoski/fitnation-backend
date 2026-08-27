<?php

namespace Tests\Unit\Services;

use App\Enums\WorkoutSessionStatus;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\MovementPattern;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\WorkoutGenerator\ProgressionCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * getLastPerformanceForExercises() replaces a per-exercise getLastPerformance()
 * loop, so it has to agree with it on every input — the ranked-window SQL and
 * the Eloquent lookup must pick the same session and shape the same array.
 */
class ProgressionCalculatorBatchTest extends TestCase
{
    use RefreshDatabase;

    private ProgressionCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProgressionCalculatorService;
    }

    public function test_batch_matches_the_single_lookup_for_every_exercise(): void
    {
        $user = User::factory()->create();

        $withHistory = $this->createExercise('SQUAT', 'BARBELL');
        $withOlderAndNewerHistory = $this->createExercise('PRESS', 'DUMBBELL');
        $withoutHistory = $this->createExercise('ROW', 'CABLE');

        $older = $this->completedSession($user, now()->subMonth());
        $newer = $this->completedSession($user, now()->subDay());

        $this->logSets($older, $withHistory, [[60.0, 10], [60.0, 9]]);
        $this->logSets($older, $withOlderAndNewerHistory, [[20.0, 12], [20.0, 12]]);
        $this->logSets($newer, $withOlderAndNewerHistory, [[22.0, 8], [22.0, 8], [24.0, 6]]);

        $exercises = collect([$withHistory, $withOlderAndNewerHistory, $withoutHistory]);

        $batch = $this->service->getLastPerformanceForExercises($exercises, $user);

        foreach ($exercises as $exercise) {
            $this->assertEquals(
                $this->service->getLastPerformance($exercise, $user),
                $batch[$exercise->id] ?? null,
                "Batched last performance diverged from the single lookup for exercise {$exercise->id}."
            );
        }
    }

    public function test_batch_picks_the_most_recently_completed_session(): void
    {
        $user = User::factory()->create();
        $exercise = $this->createExercise('SQUAT', 'BARBELL');

        $older = $this->completedSession($user, now()->subMonth());
        $newer = $this->completedSession($user, now()->subDay());

        $this->logSets($older, $exercise, [[60.0, 10]]);
        $this->logSets($newer, $exercise, [[80.0, 5], [80.0, 5]]);

        $batch = $this->service->getLastPerformanceForExercises([$exercise], $user);

        $this->assertSame(80.0, $batch[$exercise->id]['weight']);
        $this->assertSame(2, $batch[$exercise->id]['sets']);
        $this->assertSame([5, 5], $batch[$exercise->id]['reps']);
    }

    public function test_batch_ignores_sessions_that_are_not_completed(): void
    {
        $user = User::factory()->create();
        $exercise = $this->createExercise('SQUAT', 'BARBELL');

        $completed = $this->completedSession($user, now()->subMonth());
        $this->logSets($completed, $exercise, [[60.0, 10]]);

        $active = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => null,
            'performed_at' => now(),
            'completed_at' => null,
            'status' => WorkoutSessionStatus::Active,
        ]);
        $this->logSets($active, $exercise, [[100.0, 1]]);

        $batch = $this->service->getLastPerformanceForExercises([$exercise], $user);

        $this->assertSame(60.0, $batch[$exercise->id]['weight']);
    }

    public function test_batch_ignores_another_users_history(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $exercise = $this->createExercise('SQUAT', 'BARBELL');

        $theirs = $this->completedSession($otherUser, now()->subDay());
        $this->logSets($theirs, $exercise, [[200.0, 5]]);

        $this->assertSame([], $this->service->getLastPerformanceForExercises([$exercise], $user));
    }

    /**
     * The batched query reads weight through the query builder, which bypasses
     * SetLog's decimal:1 cast. Without normalisation the two paths disagree on
     * any weight with non-zero hundredths — which is every weight an imperial
     * user logs, since lbs convert to kg with a fractional remainder.
     */
    public function test_batch_matches_the_single_lookup_on_fractional_weights(): void
    {
        $user = User::factory()->create();
        $exercise = $this->createExercise('SQUAT', 'BARBELL');

        $session = $this->completedSession($user, now()->subDay());
        $this->logSets($session, $exercise, [[61.23, 10], [61.27, 10], [61.23, 9]]);

        $batch = $this->service->getLastPerformanceForExercises([$exercise], $user);

        $this->assertEquals(
            $this->service->getLastPerformance($exercise, $user),
            $batch[$exercise->id]
        );
        $this->assertSame([61.2, 61.3, 61.2], $batch[$exercise->id]['weights']);
        $this->assertSame(61.2, $batch[$exercise->id]['weight']);
    }

    /**
     * Equal completed_at across two sessions: both lookups must break the tie
     * the same way, or GET show and PUT exercise report different history.
     */
    public function test_batch_and_single_break_completed_at_ties_identically(): void
    {
        $user = User::factory()->create();
        $exercise = $this->createExercise('SQUAT', 'BARBELL');

        $sameMoment = now()->subDay()->startOfSecond();
        $first = $this->completedSession($user, $sameMoment);
        $second = $this->completedSession($user, $sameMoment);

        $this->logSets($first, $exercise, [[60.0, 10]]);
        $this->logSets($second, $exercise, [[100.0, 3]]);

        $batch = $this->service->getLastPerformanceForExercises([$exercise], $user);

        $this->assertEquals(
            $this->service->getLastPerformance($exercise, $user),
            $batch[$exercise->id]
        );
        $this->assertSame(100.0, $batch[$exercise->id]['weight'], 'Expected the higher session id to win the tie.');
    }

    public function test_batch_returns_empty_for_no_exercises(): void
    {
        $user = User::factory()->create();

        $this->assertSame([], $this->service->getLastPerformanceForExercises([], $user));
    }

    private function completedSession(User $user, \DateTimeInterface $completedAt): WorkoutSession
    {
        return WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => null,
            'performed_at' => $completedAt,
            'completed_at' => $completedAt,
            'status' => WorkoutSessionStatus::Completed,
        ]);
    }

    /**
     * @param  array<int, array{0: float, 1: int}>  $sets  [weight, reps] pairs
     */
    private function logSets(WorkoutSession $session, Exercise $exercise, array $sets): void
    {
        foreach ($sets as $index => [$weight, $reps]) {
            SetLog::create([
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => $index + 1,
                'weight' => $weight,
                'reps' => $reps,
                'rest_seconds' => 90,
            ]);
        }
    }

    private function createExercise(string $movementCode, string $equipmentCode): Exercise
    {
        $movementPattern = MovementPattern::firstOrCreate(
            ['code' => $movementCode],
            ['name' => $movementCode, 'display_order' => 1]
        );

        $equipmentType = EquipmentType::firstOrCreate(
            ['code' => $equipmentCode],
            ['name' => $equipmentCode, 'display_order' => 1]
        );

        return Exercise::factory()->create([
            'movement_pattern_id' => $movementPattern->id,
            'equipment_type_id' => $equipmentType->id,
            'angle_id' => null,
        ]);
    }
}
