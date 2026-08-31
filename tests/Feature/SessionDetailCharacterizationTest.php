<?php

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Enums\TrainingExperience;
use App\Enums\UnitSystem;
use App\Enums\WorkoutSessionStatus;
use App\Http\Resources\Api\GeneratedWorkoutSessionResource;
use App\Http\Resources\Api\WorkoutSessionResource;
use App\Models\Category;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\Partner;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Locks the JSON the workout-session read paths emit, so the SessionDetail
 * refactor can demonstrate — rather than assert — that it changed nothing.
 *
 * These tests describe current behaviour, not desired behaviour: where the two
 * differ, the test says so. Every fixture value is set explicitly rather than
 * faked, so the whole payload can be compared for equality instead of spot
 * checked — a refactor that drops a key or reorders one is then a failure, not
 * a thing someone has to notice in review.
 */
class SessionDetailCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    /** Every timestamp in the payload; the fixture is built in one frozen instant. */
    private string $ts;

    /**
     * Ids of everything the fixture created. Read rather than hardcoded:
     * RefreshDatabase rolls transactions back but does not reset auto-increment
     * counters, so a literal id passes alone and fails in the suite.
     *
     * @var array<string, int>
     */
    private array $id = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-30 10:00:00');
        $this->ts = now()->toJSON();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_show_payload_is_locked(): void
    {
        ['user' => $user, 'session' => $session] = $this->makeFixture();

        $response = $this->actingAs($user->fresh(), 'sanctum')
            ->getJson("/api/workout-sessions/{$session->id}");

        $response->assertOk();

        $this->assertSame($this->expectedSessionPayload(), $response->json('data'));
    }

    /**
     * Current behaviour, and wrong: WorkoutSessionResource emits an empty
     * exercise list unless BOTH workoutSessionExercises and setLogs are loaded,
     * and today() loads only the first. No client reads the field, so nothing
     * breaks today — but API_DOCUMENTATION.md:2704 and the front-end's own type
     * at packages/shared/src/types/api.ts:290 both declare it present.
     *
     * This assertion is expected to be replaced, not deleted, by the commit
     * that puts today() behind SessionDetail.
     */
    public function test_today_currently_returns_a_session_without_its_exercises(): void
    {
        ['user' => $user] = $this->makeFixture();

        $response = $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/workout-sessions/today');

        $response->assertOk();

        $this->assertSame([], $response->json('data.session.exercises'));
        $this->assertSame(
            ['total_exercises' => 0, 'completed_exercises' => 0, 'progress_percent' => 0],
            $response->json('data.session.progress')
        );
    }

    /**
     * The claim behind deleting GeneratedWorkoutSessionResource: it is a no-op
     * over WorkoutSessionResource. A backed enum encodes to its value, so
     * `status` and `status->value` are the same JSON, and the other three keys
     * it re-merges are already emitted by the base.
     */
    public function test_generated_resource_emits_the_same_json_as_the_base_resource(): void
    {
        ['user' => $user, 'session' => $session] = $this->makeFixture();

        $loaded = $session->fresh(WorkoutSession::detailRelations());

        $request = Request::create('/api/workout-generator/generate');
        $request->setUserResolver(fn () => $user->fresh());

        $this->assertSame(
            json_encode((new WorkoutSessionResource($loaded))->toArray($request)),
            json_encode((new GeneratedWorkoutSessionResource($loaded))->toArray($request)),
            'GeneratedWorkoutSessionResource is meant to be a no-op wrapper; it is not.'
        );
    }

    /**
     * A live session holding two exercises: the first with a completed session
     * behind it and two of three sets logged today, the second with neither.
     *
     * @return array{user: User, session: WorkoutSession}
     */
    private function makeFixture(): array
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

        $user = $user->fresh('profile');

        $category = Category::factory()->create([
            'type' => CategoryType::Workout,
            'name' => 'Strength',
            'slug' => 'strength',
            'display_order' => 10,
            'icon' => '🏋️',
            'color' => '#112233',
        ]);

        $primary = MuscleGroup::factory()->create(['name' => 'Chest', 'body_region' => 'upper']);
        $secondary = MuscleGroup::factory()->create(['name' => 'Triceps', 'body_region' => 'upper']);

        $exercises = collect([
            ['name' => 'Barbell Bench Press', 'description' => 'Press the bar off the chest.'],
            ['name' => 'Barbell Overhead Press', 'description' => 'Press the bar overhead.'],
        ])->map(function (array $attributes) use ($category, $partner, $primary, $secondary) {
            $exercise = Exercise::factory()->press()->barbell()->flat()->create($attributes + [
                'category_id' => $category->id,
                'default_rest_sec' => 90,
            ]);

            $exercise->partners()->attach($partner->id);
            $exercise->muscleGroups()->attach($primary->id, ['is_primary' => true]);
            $exercise->muscleGroups()->attach($secondary->id, ['is_primary' => false]);

            return $exercise;
        });

        $session = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => null,
            'performed_at' => now(),
            'completed_at' => null,
            'status' => WorkoutSessionStatus::Active,
            'notes' => 'Characterization fixture.',
        ]);

        $rows = $exercises->map(fn (Exercise $exercise, int $index) => WorkoutSessionExercise::create([
            'workout_session_id' => $session->id,
            'exercise_id' => $exercise->id,
            'order' => $index,
            'target_sets' => 3,
            'min_target_reps' => 8,
            'max_target_reps' => 12,
            'target_weight' => 60,
            'rest_seconds' => 90,
        ]));

        // Last week's completed session, feeding both previous_sets and the
        // progression calculator — for the first exercise only, so the payload
        // covers a row with history and a row without.
        $history = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => null,
            'performed_at' => now()->subWeek(),
            'completed_at' => now()->subWeek()->addHour(),
            'status' => WorkoutSessionStatus::Completed,
            'notes' => null,
        ]);

        $previous = [];

        foreach ([1, 2, 3] as $setNumber) {
            $previous[] = SetLog::create([
                'workout_session_id' => $history->id,
                'workout_session_exercise_id' => null,
                'exercise_id' => $exercises[0]->id,
                'set_number' => $setNumber,
                'weight' => 60,
                'reps' => 12,
                'rest_seconds' => 90,
            ]);
        }

        $logged = [];

        // Two of three sets logged today, so the first row is incomplete and
        // the session sits at 0 of 2.
        foreach ([1, 2] as $setNumber) {
            $logged[] = SetLog::create([
                'workout_session_id' => $session->id,
                'workout_session_exercise_id' => $rows[0]->id,
                'exercise_id' => $exercises[0]->id,
                'set_number' => $setNumber,
                'weight' => 62.5,
                'reps' => 10,
                'rest_seconds' => 90,
            ]);
        }

        $this->id = [
            'user' => $user->id,
            'session' => $session->id,
            'history' => $history->id,
            'category' => $category->id,
            'chest' => $primary->id,
            'triceps' => $secondary->id,
            'angle' => $exercises[0]->angle_id,
            'movementPattern' => $exercises[0]->movement_pattern_id,
            'equipmentType' => $exercises[0]->equipment_type_id,
            'exerciseA' => $exercises[0]->id,
            'exerciseB' => $exercises[1]->id,
            'rowA' => $rows[0]->id,
            'rowB' => $rows[1]->id,
            'previous1' => $previous[0]->id,
            'previous2' => $previous[1]->id,
            'previous3' => $previous[2]->id,
            'logged1' => $logged[0]->id,
            'logged2' => $logged[1]->id,
        ];

        return ['user' => $user, 'session' => $session->fresh()];
    }

    /**
     * The payload as it stands on main. Ids are the fixture's insertion order
     * under RefreshDatabase.
     *
     * @return array<string, mixed>
     */
    private function expectedSessionPayload(): array
    {
        return [
            'id' => $this->id['session'],
            'user_id' => $this->id['user'],
            'workout_template_id' => null,
            'performed_at' => $this->ts,
            'completed_at' => null,
            'status' => 'active',
            'rationale' => 'Characterization fixture.',
            'is_auto_generated' => false,
            'replaced_session_id' => null,
            'notes' => 'Characterization fixture.',
            'exercises' => [
                [
                    'session_exercise' => [
                        'id' => $this->id['rowA'],
                        'workout_session_id' => $this->id['session'],
                        'exercise_id' => $this->id['exerciseA'],
                        'exercise' => $this->expectedExercise(
                            $this->id['exerciseA'],
                            'Barbell Bench Press',
                            'Press the bar off the chest.'
                        ),
                        'order' => 0,
                        'target_sets' => 3,
                        'min_target_reps' => 8,
                        'max_target_reps' => 12,
                        'progression_mode' => 'double_progression',
                        // 3 × 12 reps at 60kg against a max of 12: double
                        // progression fires, 60 + 2.5 barbell increment.
                        'progression_status' => 'ready',
                        'target_weight' => 62.5,
                        'total_reps_previous' => null,
                        'total_reps_target' => null,
                        'rest_seconds' => 90,
                        'created_at' => $this->ts,
                        'updated_at' => $this->ts,
                    ],
                    'logged_sets' => [
                        $this->expectedSetLog($this->id['logged1'], $this->id['session'], $this->id['rowA'], 1, 62.5, 10),
                        $this->expectedSetLog($this->id['logged2'], $this->id['session'], $this->id['rowA'], 2, 62.5, 10),
                    ],
                    'previous_sets' => [
                        $this->expectedSetLog($this->id['previous1'], $this->id['history'], null, 1, 60, 12),
                        $this->expectedSetLog($this->id['previous2'], $this->id['history'], null, 2, 60, 12),
                        $this->expectedSetLog($this->id['previous3'], $this->id['history'], null, 3, 60, 12),
                    ],
                    // Two logged sets against a stored target of 3.
                    'is_completed' => false,
                ],
                [
                    'session_exercise' => [
                        'id' => $this->id['rowB'],
                        'workout_session_id' => $this->id['session'],
                        'exercise_id' => $this->id['exerciseB'],
                        'exercise' => $this->expectedExercise(
                            $this->id['exerciseB'],
                            'Barbell Overhead Press',
                            'Press the bar overhead.'
                        ),
                        'order' => 1,
                        'target_sets' => 3,
                        'min_target_reps' => 8,
                        'max_target_reps' => 12,
                        'progression_mode' => 'double_progression',
                        'progression_status' => 'no_history',
                        // Beginner male barbell flat press with no history:
                        // 80kg × 0.65 PRESS base × 0.6 experience = 31.2kg,
                        // rounded down to the 2.5kg barbell increment.
                        'target_weight' => 30,
                        'total_reps_previous' => null,
                        'total_reps_target' => null,
                        'rest_seconds' => 90,
                        'created_at' => $this->ts,
                        'updated_at' => $this->ts,
                    ],
                    'logged_sets' => [],
                    'previous_sets' => [],
                    'is_completed' => false,
                ],
            ],
            'progress' => [
                'total_exercises' => 2,
                'completed_exercises' => 0,
                'progress_percent' => 0,
            ],
            'created_at' => $this->ts,
            'updated_at' => $this->ts,
        ];
    }

    /**
     * Note the absence of `target_region`: ExerciseResource emits it only
     * whenLoaded, and the session relation set does not load it.
     *
     * @return array<string, mixed>
     */
    private function expectedExercise(int $id, string $name, string $description): array
    {
        $chest = ['id' => $this->id['chest'], 'name' => 'Chest', 'body_region' => 'upper', 'is_primary' => true, 'created_at' => $this->ts, 'updated_at' => $this->ts];
        $triceps = ['id' => $this->id['triceps'], 'name' => 'Triceps', 'body_region' => 'upper', 'is_primary' => false, 'created_at' => $this->ts, 'updated_at' => $this->ts];

        return [
            'id' => $id,
            'category' => [
                'id' => $this->id['category'],
                'type' => 'workout',
                'name' => 'Strength',
                'slug' => 'strength',
                'display_order' => 10,
                'icon' => '🏋️',
                'color' => '#112233',
                'created_at' => $this->ts,
                'updated_at' => $this->ts,
            ],
            'muscle_groups' => [$chest, $triceps],
            'primary_muscle_groups' => [$chest],
            'secondary_muscle_groups' => [$triceps],
            'angle' => ['id' => $this->id['angle'], 'code' => 'FLAT', 'name' => 'Flat', 'display_order' => 10],
            'movement_pattern' => ['id' => $this->id['movementPattern'], 'code' => 'PRESS', 'name' => 'Press', 'display_order' => 10],
            'equipment_type' => ['id' => $this->id['equipmentType'], 'code' => 'BARBELL', 'name' => 'Barbell', 'display_order' => 10],
            'name' => $name,
            'description' => $description,
            'muscle_group_image' => null,
            'image' => null,
            'video' => null,
            'default_rest_sec' => 90,
            'created_at' => $this->ts,
            'updated_at' => $this->ts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedSetLog(int $id, int $sessionId, ?int $rowId, int $setNumber, float|int $weight, int $reps): array
    {
        return [
            'id' => $id,
            'workout_session_id' => $sessionId,
            'workout_session_exercise_id' => $rowId,
            // Every set in the fixture belongs to the first exercise; the
            // second row has none.
            'exercise_id' => $this->id['exerciseA'],
            'set_number' => $setNumber,
            'weight' => $weight,
            'reps' => $reps,
            'rest_seconds' => 90,
            'created_at' => $this->ts,
            'updated_at' => $this->ts,
        ];
    }
}
