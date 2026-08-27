<?php

namespace Tests\Feature;

use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Enums\TrainingExperience;
use App\Enums\UnitSystem;
use App\Enums\WorkoutSessionStatus;
use App\Models\Category;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\Partner;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards issue 001: serializing a workout session must not fan out queries
 * per exercise row. The progression lookups are batched once per request, so
 * the query count is flat in the number of exercises.
 */
class WorkoutSessionResourceQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_query_count_does_not_grow_with_exercise_count(): void
    {
        $user = $this->makeUser();

        $small = $this->makeSessionWithHistory($user, 1);
        $large = $this->makeSessionWithHistory($user, 12);

        $smallQueries = $this->countQueriesForShow($user, $small);
        $largeQueries = $this->countQueriesForShow($user, $large);

        $this->assertSame(
            $smallQueries,
            $largeQueries,
            "Query count scaled with exercise count: {$smallQueries} query/queries for 1 exercise, ".
            "{$largeQueries} for 12. The per-row progression lookups must be batched."
        );
    }

    public function test_show_returns_the_same_progression_values_as_the_per_row_calculation(): void
    {
        $user = $this->makeUser();
        $session = $this->makeSessionWithHistory($user, 3);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/workout-sessions/{$session->id}");

        $response->assertOk();

        $exercises = $response->json('data.exercises');
        $this->assertCount(3, $exercises);

        foreach ($exercises as $exercise) {
            $sessionExercise = $exercise['session_exercise'];

            // History is 3 sets of 12 reps at 60kg against a max_target_reps of
            // 12, so double progression fires: 60 + 2.5 (barbell increment).
            $this->assertSame('double_progression', $sessionExercise['progression_mode']);
            $this->assertSame('ready', $sessionExercise['progression_status']);
            $this->assertEqualsWithDelta(62.5, $sessionExercise['target_weight'], 0.001);
            $this->assertSame(3, $sessionExercise['target_sets']);
            $this->assertSame(8, $sessionExercise['min_target_reps']);
            $this->assertSame(12, $sessionExercise['max_target_reps']);
            $this->assertSame(90, $sessionExercise['rest_seconds']);
            $this->assertNull($sessionExercise['total_reps_previous']);
            $this->assertNull($sessionExercise['total_reps_target']);
        }
    }

    public function test_show_returns_default_targets_for_an_exercise_with_no_history(): void
    {
        $user = $this->makeUser();
        $session = $this->makeSession($user, 1);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/workout-sessions/{$session->id}");

        $response->assertOk();

        $sessionExercise = $response->json('data.exercises.0.session_exercise');

        $this->assertSame('double_progression', $sessionExercise['progression_mode']);
        $this->assertSame('no_history', $sessionExercise['progression_status']);
        $this->assertSame(3, $sessionExercise['target_sets']);
        $this->assertSame(8, $sessionExercise['min_target_reps']);
        $this->assertSame(12, $sessionExercise['max_target_reps']);

        // Beginner male barbell flat press: 80kg body weight × 0.65 (PRESS base)
        // × 1.0 gender × 0.6 experience × 1.0 equipment × 1.0 angle = 31.2kg,
        // rounded down to the 2.5kg barbell increment.
        $this->assertEqualsWithDelta(30.0, $sessionExercise['target_weight'], 0.001);
    }

    public function test_show_exposes_the_exercise_relations_the_client_reads(): void
    {
        $user = $this->makeUser();
        $session = $this->makeSession($user, 1);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/workout-sessions/{$session->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'exercises' => [
                        [
                            'session_exercise' => [
                                'exercise' => [
                                    'id',
                                    'name',
                                    'category',
                                    'muscle_groups',
                                    'primary_muscle_groups',
                                    'secondary_muscle_groups',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    }

    /**
     * addExercise/updateExercise/reorderExercises return a bare
     * WorkoutSessionExerciseResource, which has no batched progression handed to
     * it and resolves its own. That fallback must agree with the batched path.
     */
    public function test_single_row_endpoints_report_the_same_progression_as_the_session_view(): void
    {
        $user = $this->makeUser();
        $session = $this->makeSessionWithHistory($user, 2);
        $sessionExercise = $session->workoutSessionExercises->first();

        $fromSession = collect(
            $this->actingAs($user->fresh(), 'sanctum')
                ->getJson("/api/workout-sessions/{$session->id}")
                ->assertOk()
                ->json('data.exercises')
        )->firstWhere('session_exercise.id', $sessionExercise->id)['session_exercise'];

        $fromUpdate = $this->actingAs($user->fresh(), 'sanctum')
            ->putJson("/api/workout-sessions/{$session->id}/exercises/{$sessionExercise->id}", [
                'target_sets' => 3,
            ])
            ->assertOk()
            ->json('data');

        foreach (['progression_mode', 'progression_status', 'target_weight', 'target_sets', 'min_target_reps', 'max_target_reps', 'rest_seconds'] as $key) {
            $this->assertSame(
                $fromSession[$key],
                $fromUpdate[$key],
                "Single-row fallback disagreed with the batched path on '{$key}'."
            );
        }

        $this->assertSame(
            $fromSession['exercise']['id'],
            $fromUpdate['exercise']['id']
        );
        $this->assertArrayHasKey('muscle_groups', $fromUpdate['exercise']);
    }

    public function test_reorder_query_count_does_not_grow_with_exercise_count(): void
    {
        $user = $this->makeUser();

        $small = $this->makeSessionWithHistory($user, 1);
        $large = $this->makeSessionWithHistory($user, 8);

        $smallQueries = $this->countQueriesForReorder($user, $small);
        $largeQueries = $this->countQueriesForReorder($user, $large);

        $this->assertSame(
            $smallQueries,
            $largeQueries,
            "Reorder query count scaled with exercise count: {$smallQueries} for 1 exercise, ".
            "{$largeQueries} for 8. WorkoutSessionExerciseResource::collection() leaves each row ".
            'to resolve its own progression; use collectionForRows().'
        );
    }

    public function test_reorder_returns_every_exercise_with_its_relations(): void
    {
        $user = $this->makeUser();
        $session = $this->makeSessionWithHistory($user, 6);
        $ids = $session->workoutSessionExercises->pluck('id')->reverse()->values()->all();

        $response = $this->actingAs($user->fresh(), 'sanctum')
            ->postJson("/api/workout-sessions/{$session->id}/exercises/reorder", [
                'exercise_ids' => $ids,
            ]);

        $response->assertOk();
        $this->assertCount(6, $response->json('data'));
        $this->assertArrayHasKey('muscle_groups', $response->json('data.0.exercise'));
        $this->assertSame('ready', $response->json('data.0.progression_status'));
    }

    private function countQueriesForReorder(User $user, WorkoutSession $session): int
    {
        $ids = $session->workoutSessionExercises->pluck('id')->reverse()->values()->all();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user->fresh(), 'sanctum')
            ->postJson("/api/workout-sessions/{$session->id}/exercises/reorder", ['exercise_ids' => $ids])
            ->assertOk();

        // The reorder writes one UPDATE per row by design; only the read-side
        // fan-out is under test here.
        // Two per-row costs are out of scope for the serialization fix under
        // test: the UPDATE the reorder performs per row by design, and the
        // `exists` rule in ReorderSessionExercisesRequest, which validates each
        // id with its own SELECT COUNT (tracked separately as issue 006).
        $count = collect(DB::getQueryLog())
            ->reject(fn ($entry) => str_starts_with(strtolower($entry['query']), 'update'))
            ->reject(fn ($entry) => str_contains($entry['query'], 'count(*) as aggregate'))
            ->count();

        DB::disableQueryLog();
        DB::flushQueryLog();

        return $count;
    }

    private function countQueriesForShow(User $user, WorkoutSession $session): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        // A fresh instance per request: actingAs() authenticates the object it
        // is handed, so a reused $user carries relations cached by the previous
        // request and undercounts the second one.
        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson("/api/workout-sessions/{$session->id}")
            ->assertOk();

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();
        DB::flushQueryLog();

        return $count;
    }

    private function makeUser(): User
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);

        $user->profile()->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Beginner,
            'gender' => Gender::Male,
            'weight' => 80,
            'height' => 180,
            'unit_system' => UnitSystem::Metric,
        ]);

        return $user->fresh('profile');
    }

    /**
     * An active session with $count barbell press exercises, each fully
     * classified and attached to the user's partner.
     */
    private function makeSession(User $user, int $count): WorkoutSession
    {
        $category = Category::factory()->create();
        $primary = MuscleGroup::factory()->create();
        $secondary = MuscleGroup::factory()->create();

        $session = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => null,
            'performed_at' => now(),
            'completed_at' => null,
            'status' => WorkoutSessionStatus::Active,
        ]);

        $exercises = Exercise::factory()
            ->count($count)
            ->press()
            ->barbell()
            ->flat()
            ->create(['category_id' => $category->id, 'default_rest_sec' => 90]);

        foreach ($exercises as $index => $exercise) {
            $exercise->partners()->attach($user->partner_id);
            $exercise->muscleGroups()->attach($primary->id, ['is_primary' => true]);
            $exercise->muscleGroups()->attach($secondary->id, ['is_primary' => false]);

            WorkoutSessionExercise::create([
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'order' => $index,
                'target_sets' => 3,
                'min_target_reps' => 8,
                'max_target_reps' => 12,
                'target_weight' => 60,
                'rest_seconds' => 90,
            ]);
        }

        return $session->fresh();
    }

    /**
     * The same session, plus one completed session per exercise holding
     * 3 × 12 reps at 60kg so the progression calculator has history to read.
     */
    private function makeSessionWithHistory(User $user, int $count): WorkoutSession
    {
        $session = $this->makeSession($user, $count);

        $history = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => null,
            'performed_at' => now()->subWeek(),
            'completed_at' => now()->subWeek()->addHour(),
            'status' => WorkoutSessionStatus::Completed,
        ]);

        foreach ($session->workoutSessionExercises as $sessionExercise) {
            foreach ([1, 2, 3] as $setNumber) {
                SetLog::create([
                    'workout_session_id' => $history->id,
                    'exercise_id' => $sessionExercise->exercise_id,
                    'set_number' => $setNumber,
                    'weight' => 60,
                    'reps' => 12,
                    'rest_seconds' => 90,
                ]);
            }
        }

        return $session;
    }
}
