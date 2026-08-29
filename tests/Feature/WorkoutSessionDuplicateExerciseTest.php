<?php

namespace Tests\Feature;

use App\Enums\FitnessGoal;
use App\Enums\TrainingExperience;
use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\Partner;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards issue 002: set logs belong to a session-exercise row, not to an
 * (session, exercise) pair. The same exercise may appear twice in a session —
 * a top set and a back-off block, or a superset repeat — and each row owns its
 * own sets.
 */
class WorkoutSessionDuplicateExerciseTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_duplicate_row_shows_only_its_own_sets(): void
    {
        [$user, $session, $first, $second] = $this->sessionWithDuplicateExercise();

        $this->logSetsOn($first, [[100.0, 5], [100.0, 5]]);
        $this->logSetsOn($second, [[60.0, 12]]);

        $exercises = $this->actingAs($user, 'sanctum')
            ->getJson("/api/workout-sessions/{$session->id}")
            ->assertOk()
            ->json('data.exercises');

        $this->assertCount(2, $exercises);

        $firstRow = collect($exercises)->firstWhere('session_exercise.id', $first->id);
        $secondRow = collect($exercises)->firstWhere('session_exercise.id', $second->id);

        $this->assertCount(2, $firstRow['logged_sets']);
        $this->assertCount(1, $secondRow['logged_sets']);
        $this->assertEqualsCanonicalizing([100.0, 100.0], array_column($firstRow['logged_sets'], 'weight'));
        $this->assertEqualsCanonicalizing([60.0], array_column($secondRow['logged_sets'], 'weight'));
    }

    public function test_progress_counts_duplicate_rows_independently(): void
    {
        [$user, $session, $first, $second] = $this->sessionWithDuplicateExercise();

        // The first row hits its 2-set target; the second logs nothing.
        $first->update(['target_sets' => 2]);
        $second->update(['target_sets' => 2]);
        $this->logSetsOn($first, [[100.0, 5], [100.0, 5]]);

        $progress = $this->actingAs($user, 'sanctum')
            ->getJson("/api/workout-sessions/{$session->id}")
            ->assertOk()
            ->json('data.progress');

        $this->assertSame(2, $progress['total_exercises']);
        $this->assertSame(1, $progress['completed_exercises'], 'The unlogged duplicate row must not inherit the other row\'s sets.');
        $this->assertEqualsWithDelta(50.0, $progress['progress_percent'], 0.001);
    }

    public function test_removing_one_duplicate_row_leaves_the_others_sets_intact(): void
    {
        [$user, $session, $first, $second] = $this->sessionWithDuplicateExercise();

        $this->logSetsOn($first, [[100.0, 5]]);
        $this->logSetsOn($second, [[60.0, 12], [60.0, 10]]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/workout-sessions/{$session->id}/exercises/{$first->id}")
            ->assertOk();

        $this->assertSame(0, SetLog::where('workout_session_exercise_id', $first->id)->count());
        $this->assertSame(2, SetLog::where('workout_session_exercise_id', $second->id)->count());
    }

    public function test_deleting_a_set_resequences_only_its_own_row(): void
    {
        [$user, $session, $first, $second] = $this->sessionWithDuplicateExercise();

        $this->logSetsOn($first, [[100.0, 5], [100.0, 4], [100.0, 3], [100.0, 2]]);
        $this->logSetsOn($second, [[60.0, 12], [60.0, 11], [60.0, 10]]);

        $secondSetOfFirstRow = SetLog::where('workout_session_exercise_id', $first->id)
            ->where('set_number', 2)
            ->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/workout-sessions/{$session->id}/sets/{$secondSetOfFirstRow->id}")
            ->assertOk();

        $this->assertSame(
            [1, 2, 3],
            SetLog::where('workout_session_exercise_id', $first->id)->orderBy('set_number')->pluck('set_number')->all(),
            'The edited row must be contiguous 1..N after the delete.'
        );
        $this->assertSame(
            [1, 2, 3],
            SetLog::where('workout_session_exercise_id', $second->id)->orderBy('set_number')->pluck('set_number')->all(),
            'The duplicate row\'s numbering must be untouched.'
        );
        $this->assertEqualsCanonicalizing(
            [12, 11, 10],
            SetLog::where('workout_session_exercise_id', $second->id)->pluck('reps')->all()
        );
    }

    public function test_swapping_an_exercise_deletes_the_sets_logged_against_it(): void
    {
        [$user, $session, $first, $second] = $this->sessionWithDuplicateExercise();
        $replacement = $this->makeExercise($user);

        $this->logSetsOn($first, [[100.0, 5], [100.0, 5]]);
        $this->logSetsOn($second, [[60.0, 12]]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/workout-sessions/{$session->id}/exercises/{$first->id}/swap", [
                'exercise_id' => $replacement->id,
            ])
            ->assertOk();

        $this->assertSame($replacement->id, $first->fresh()->exercise_id);
        $this->assertSame(0, SetLog::where('workout_session_exercise_id', $first->id)->count());
        $this->assertSame(1, SetLog::where('workout_session_exercise_id', $second->id)->count(), 'The untouched row keeps its sets.');
        $this->assertSame(
            0,
            SetLog::where('workout_session_id', $session->id)->whereNull('workout_session_exercise_id')->count(),
            'A swap must not leave orphaned set logs behind.'
        );
    }

    public function test_log_set_accepts_the_session_exercise_row_id(): void
    {
        [$user, $session, $first, $second] = $this->sessionWithDuplicateExercise();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/workout-sessions/{$session->id}/sets", [
                'workout_session_exercise_id' => $second->id,
                'exercise_id' => $second->exercise_id,
                'set_number' => 1,
                'weight' => 60,
                'reps' => 12,
            ])
            ->assertCreated();

        $setLog = SetLog::findOrFail($response->json('data.id'));

        $this->assertSame($second->id, $setLog->workout_session_exercise_id);
        $this->assertSame(0, SetLog::where('workout_session_exercise_id', $first->id)->count());
    }

    /**
     * Deployed mobile builds post exercise_id only. The server must still place
     * the set on a row rather than storing it unattached.
     */
    public function test_log_set_without_a_row_id_resolves_the_row_from_the_exercise(): void
    {
        $user = $this->makeUser();
        $session = $this->makeSession($user);
        $exercise = $this->makeExercise($user);
        $row = $this->addExerciseRow($session, $exercise, 0);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/workout-sessions/{$session->id}/sets", [
                'exercise_id' => $exercise->id,
                'set_number' => 1,
                'weight' => 80,
                'reps' => 8,
            ])
            ->assertCreated();

        $this->assertSame($row->id, SetLog::findOrFail($response->json('data.id'))->workout_session_exercise_id);
    }

    public function test_log_set_rejects_a_row_belonging_to_another_session(): void
    {
        [$user, $session, $first] = $this->sessionWithDuplicateExercise();
        $otherSession = $this->makeSession($user);
        $foreignRow = $this->addExerciseRow($otherSession, $this->makeExercise($user), 0);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/workout-sessions/{$session->id}/sets", [
                'workout_session_exercise_id' => $foreignRow->id,
                'exercise_id' => $foreignRow->exercise_id,
                'set_number' => 1,
                'weight' => 60,
                'reps' => 12,
            ])
            ->assertStatus(422);
    }

    /**
     * The column is nullable through this transition, so sets written before it
     * existed (or orphaned by an old swap) must stay visible rather than
     * silently vanishing from the response.
     */
    public function test_legacy_sets_without_a_row_id_remain_visible(): void
    {
        $user = $this->makeUser();
        $session = $this->makeSession($user);
        $exercise = $this->makeExercise($user);
        $row = $this->addExerciseRow($session, $exercise, 0);

        SetLog::create([
            'workout_session_id' => $session->id,
            'workout_session_exercise_id' => null,
            'exercise_id' => $exercise->id,
            'set_number' => 1,
            'weight' => 75,
            'reps' => 9,
            'rest_seconds' => 90,
        ]);

        $exercises = $this->actingAs($user, 'sanctum')
            ->getJson("/api/workout-sessions/{$session->id}")
            ->assertOk()
            ->json('data.exercises');

        $this->assertCount(1, $exercises[0]['logged_sets']);
        $this->assertEqualsWithDelta(75.0, $exercises[0]['logged_sets'][0]['weight'], 0.001);
        $this->assertSame($row->id, $exercises[0]['session_exercise']['id']);
    }

    public function test_removing_a_row_also_clears_its_legacy_sets(): void
    {
        $user = $this->makeUser();
        $session = $this->makeSession($user);
        $exercise = $this->makeExercise($user);
        $row = $this->addExerciseRow($session, $exercise, 0);

        SetLog::create([
            'workout_session_id' => $session->id,
            'workout_session_exercise_id' => null,
            'exercise_id' => $exercise->id,
            'set_number' => 1,
            'weight' => 75,
            'reps' => 9,
            'rest_seconds' => 90,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/workout-sessions/{$session->id}/exercises/{$row->id}")
            ->assertOk();

        $this->assertSame(0, SetLog::where('workout_session_id', $session->id)->count());
    }

    public function test_log_set_rejects_a_row_that_disagrees_with_the_exercise(): void
    {
        $user = $this->makeUser();
        $session = $this->makeSession($user);
        $squat = $this->makeExercise($user);
        $bench = $this->makeExercise($user);
        $squatRow = $this->addExerciseRow($session, $squat, 0);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/workout-sessions/{$session->id}/sets", [
                'workout_session_exercise_id' => $squatRow->id,
                'exercise_id' => $bench->id,
                'set_number' => 1,
                'weight' => 60,
                'reps' => 10,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('exercise_id');

        $this->assertSame(0, SetLog::where('workout_session_id', $session->id)->count());
    }

    /**
     * A row can hold both kinds of set during a rolling deploy: old instances
     * keep writing a null row id after the migration lands. Re-sequencing has to
     * cover both or the row is left with a hole.
     */
    public function test_deleting_a_set_resequences_across_legacy_and_attached_sets(): void
    {
        $user = $this->makeUser();
        $session = $this->makeSession($user);
        $exercise = $this->makeExercise($user);
        $row = $this->addExerciseRow($session, $exercise, 0);

        foreach ([1, 2] as $setNumber) {
            $this->legacySet($session, $exercise, $setNumber);
        }
        foreach ([3, 4] as $setNumber) {
            SetLog::create([
                'workout_session_id' => $session->id,
                'workout_session_exercise_id' => $row->id,
                'exercise_id' => $exercise->id,
                'set_number' => $setNumber,
                'weight' => 60,
                'reps' => 10,
                'rest_seconds' => 90,
            ]);
        }

        $first = SetLog::where('workout_session_id', $session->id)->where('set_number', 1)->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/workout-sessions/{$session->id}/sets/{$first->id}")
            ->assertOk();

        $this->assertSame(
            [1, 2, 3],
            SetLog::where('workout_session_id', $session->id)->orderBy('set_number')->pluck('set_number')->all()
        );
    }

    /**
     * With the exercise on two rows, a legacy set belongs to neither
     * identifiably — showing it under both is the double-count this change
     * exists to remove.
     */
    public function test_a_legacy_set_is_not_shown_under_duplicate_rows(): void
    {
        [$user, $session, $first, $second] = $this->sessionWithDuplicateExercise();

        $this->legacySet($session, $first->exercise, 1);

        $exercises = $this->actingAs($user, 'sanctum')
            ->getJson("/api/workout-sessions/{$session->id}")
            ->assertOk()
            ->json('data.exercises');

        $this->assertCount(0, collect($exercises)->firstWhere('session_exercise.id', $first->id)['logged_sets']);
        $this->assertCount(0, collect($exercises)->firstWhere('session_exercise.id', $second->id)['logged_sets']);
    }

    public function test_removing_one_duplicate_row_does_not_sweep_unattributable_legacy_sets(): void
    {
        [$user, $session, $first] = $this->sessionWithDuplicateExercise();

        $this->legacySet($session, $first->exercise, 1);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/workout-sessions/{$session->id}/exercises/{$first->id}")
            ->assertOk();

        $this->assertSame(
            1,
            SetLog::where('workout_session_id', $session->id)->whereNull('workout_session_exercise_id')->count(),
            'A legacy set that could belong to either row must survive removal of one of them.'
        );
    }

    // ---------------------------------------------------------------- helpers

    private function legacySet(WorkoutSession $session, Exercise $exercise, int $setNumber): void
    {
        SetLog::create([
            'workout_session_id' => $session->id,
            'workout_session_exercise_id' => null,
            'exercise_id' => $exercise->id,
            'set_number' => $setNumber,
            'weight' => 60,
            'reps' => 10,
            'rest_seconds' => 90,
        ]);
    }

    /**
     * @return array{0: User, 1: WorkoutSession, 2: WorkoutSessionExercise, 3: WorkoutSessionExercise}
     */
    private function sessionWithDuplicateExercise(): array
    {
        $user = $this->makeUser();
        $session = $this->makeSession($user);
        $exercise = $this->makeExercise($user);

        $first = $this->addExerciseRow($session, $exercise, 0);
        $second = $this->addExerciseRow($session, $exercise, 1);
        $first->setRelation('exercise', $exercise);
        $second->setRelation('exercise', $exercise);

        return [$user, $session, $first, $second];
    }

    private function makeUser(): User
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);

        $user->profile()->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Beginner,
        ]);

        return $user->fresh('profile');
    }

    private function makeSession(User $user): WorkoutSession
    {
        return WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => null,
            'performed_at' => now(),
            'completed_at' => null,
            'status' => WorkoutSessionStatus::Active,
        ]);
    }

    private function makeExercise(User $user): Exercise
    {
        $exercise = Exercise::factory()->press()->barbell()->flat()->create();
        $exercise->partners()->attach($user->partner_id);

        return $exercise;
    }

    private function addExerciseRow(WorkoutSession $session, Exercise $exercise, int $order): WorkoutSessionExercise
    {
        return WorkoutSessionExercise::create([
            'workout_session_id' => $session->id,
            'exercise_id' => $exercise->id,
            'order' => $order,
            'target_sets' => 3,
            'min_target_reps' => 8,
            'max_target_reps' => 12,
            'target_weight' => 60,
            'rest_seconds' => 90,
        ]);
    }

    /**
     * @param  array<int, array{0: float, 1: int}>  $sets
     */
    private function logSetsOn(WorkoutSessionExercise $row, array $sets): void
    {
        foreach ($sets as $index => [$weight, $reps]) {
            SetLog::create([
                'workout_session_id' => $row->workout_session_id,
                'workout_session_exercise_id' => $row->id,
                'exercise_id' => $row->exercise_id,
                'set_number' => $index + 1,
                'weight' => $weight,
                'reps' => $reps,
                'rest_seconds' => 90,
            ]);
        }
    }
}
