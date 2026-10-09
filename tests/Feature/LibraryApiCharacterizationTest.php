<?php

namespace Tests\Feature;

use App\Enums\PlanType;
use App\Models\Exercise;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\User;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Locks the JSON the mobile app's Program Library, clone and Routines
 * endpoints emit, before spec 025 deletes the web library-programs UI next to
 * them. The cleanup must leave these payloads byte for byte as they are.
 *
 * Ids are read from the fixture: RefreshDatabase does not reset
 * auto-increment counters.
 */
class LibraryApiCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private string $ts;

    /** @var array<string, int> */
    private array $id = [];

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-15 10:00:00');
        $this->ts = now()->toJSON();
        $this->makeFixture();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_program_library_payload_is_locked(): void
    {
        $response = $this->actingAs($this->member, 'sanctum')->getJson('/api/programs/library')->assertOk();

        $this->assertSame([$this->expectedLibraryProgram()], $response->json('data'));
    }

    public function test_the_routines_payload_is_locked(): void
    {
        $response = $this->actingAs($this->member, 'sanctum')->getJson('/api/routines')->assertOk();

        $this->assertSame([$this->expectedRoutine()], $response->json('data'));
    }

    public function test_the_single_routine_payload_is_locked(): void
    {
        $response = $this->actingAs($this->member, 'sanctum')->getJson("/api/routines/{$this->id['routine']}")->assertOk();

        $this->assertSame($this->expectedRoutine(), $response->json('data'));
    }

    public function test_the_clone_payload_is_locked(): void
    {
        $response = $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/programs/{$this->id['program']}/clone")
            ->assertCreated();

        $clone = Plan::where('user_id', $this->member->id)->where('type', PlanType::Program)->sole();
        $workout = $clone->workoutTemplates()->sole();
        $row = $workout->workoutTemplateExercises()->sole();

        $this->assertSame('Program cloned successfully', $response->json('message'));
        $this->assertSame([
            'id' => $clone->id,
            'name' => 'Strength Foundations',
            'description' => 'Eight weeks of the basics.',
            'cover_image' => null,
            'duration_weeks' => 8,
            'is_active' => false,
            'is_auto_generated' => false,
            'is_library_plan' => false,
            'progress_percentage' => 0,
            'next_workout' => $this->expectedProgramWorkout($clone->id, $workout->id, $row->id),
            'current_active_week' => 1,
            'workout_templates' => [$this->expectedProgramWorkout($clone->id, $workout->id, $row->id)],
            'created_at' => $this->ts,
            'updated_at' => $this->ts,
        ], $response->json('data'));

        // The library plan itself is untouched.
        $this->assertSame($this->id['partner'], Plan::find($this->id['program'])->partner_id);
        $this->assertSame(PlanType::Program, $clone->type);
        $this->assertNull($clone->partner_id);
    }

    private function makeFixture(): void
    {
        $partner = Partner::factory()->create(['name' => 'Iron Temple']);
        $this->member = User::factory()->entitled()->create(['partner_id' => $partner->id]);

        $squat = Exercise::factory()->create([
            'name' => 'Back Squat', 'description' => 'Catalogue cues', 'image' => null, 'video' => null,
            'muscle_group_image' => null, 'default_rest_sec' => 120,
        ]);
        $plank = Exercise::factory()->create([
            'name' => 'Plank', 'description' => 'Brace', 'image' => null, 'video' => null,
            'muscle_group_image' => null, 'default_rest_sec' => 60,
        ]);
        $partner->exercises()->attach([$squat->id, $plank->id]);

        $program = Plan::factory()->partnerLibrary($partner)->create([
            'name' => 'Strength Foundations', 'description' => 'Eight weeks of the basics.', 'cover_image' => null,
            'duration_weeks' => 8, 'is_active' => true, 'is_auto_generated' => false,
        ]);
        $programWorkout = WorkoutTemplate::factory()->create([
            'plan_id' => $program->id, 'name' => 'Lower A', 'description' => 'Squat day',
            'day_of_week' => 0, 'week_number' => 1, 'order_index' => 0,
        ]);
        $programRow = WorkoutTemplateExercise::create([
            'workout_template_id' => $programWorkout->id, 'exercise_id' => $squat->id, 'order' => 0,
            'target_sets' => 5, 'min_target_reps' => 5, 'max_target_reps' => 5, 'target_weight' => 60, 'rest_seconds' => 180,
        ]);

        $routine = Plan::factory()->partnerRoutine($partner)->create([
            'name' => 'Morning Mobility', 'description' => 'Ten minutes a day.', 'cover_image' => null,
        ]);
        $routineWorkout = WorkoutTemplate::factory()->create([
            'plan_id' => $routine->id, 'name' => 'Daily Flow', 'description' => 'Loosen up',
            'day_of_week' => 2, 'week_number' => 1, 'order_index' => 0,
        ]);
        $routineRow = WorkoutTemplateExercise::create([
            'workout_template_id' => $routineWorkout->id, 'exercise_id' => $plank->id, 'order' => 0,
            'target_sets' => 3, 'min_target_reps' => 30, 'max_target_reps' => 60, 'target_weight' => 0, 'rest_seconds' => 45,
        ]);

        // Noise the endpoints must leave out: another partner's library, an
        // inactive library plan, and the member's own plan.
        Plan::factory()->partnerLibrary(Partner::factory()->create())->create(['is_active' => true]);
        Plan::factory()->partnerRoutine(Partner::factory()->create())->create();
        Plan::factory()->partnerLibrary($partner)->create(['is_active' => false]);
        Plan::factory()->partnerRoutine($partner)->create(['is_active' => false]);
        Plan::factory()->create(['user_id' => $this->member->id, 'partner_id' => null, 'type' => PlanType::Routine, 'is_active' => true]);

        $this->id = [
            'partner' => $partner->id,
            'squat' => $squat->id,
            'plank' => $plank->id,
            'program' => $program->id,
            'program_workout' => $programWorkout->id,
            'program_row' => $programRow->id,
            'routine' => $routine->id,
            'routine_workout' => $routineWorkout->id,
            'routine_row' => $routineRow->id,
        ];
    }

    /**
     * A library plan has no owner, so no progress keys.
     *
     * @return array<string, mixed>
     */
    private function expectedLibraryProgram(): array
    {
        return [
            'id' => $this->id['program'],
            'name' => 'Strength Foundations',
            'description' => 'Eight weeks of the basics.',
            'cover_image' => null,
            'duration_weeks' => 8,
            'is_active' => true,
            'is_auto_generated' => false,
            'is_library_plan' => true,
            'workout_templates' => [$this->expectedProgramWorkout($this->id['program'], $this->id['program_workout'], $this->id['program_row'])],
            'created_at' => $this->ts,
            'updated_at' => $this->ts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedProgramWorkout(int $planId, int $workoutId, int $rowId): array
    {
        return [
            'id' => $workoutId,
            'plan_id' => $planId,
            'name' => 'Lower A',
            'description' => 'Squat day',
            'day_of_week' => 0,
            'week_number' => 1,
            'order_index' => 0,
            'last_completed_session_id' => null,
            'exercises' => [[
                'id' => $this->id['squat'],
                'name' => 'Back Squat',
                'description' => 'Catalogue cues',
                'image' => null,
                'video' => null,
                'muscle_group_image' => null,
                'default_rest_sec' => 120,
                'category' => null,
                'muscle_groups' => [],
                'pivot' => [
                    'id' => $rowId,
                    'order' => 0,
                    'target_sets' => 5,
                    'min_target_reps' => 5,
                    'max_target_reps' => 5,
                    'target_weight' => 60,
                    'rest_seconds' => 180,
                ],
            ]],
            'created_at' => $this->ts,
            'updated_at' => $this->ts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedRoutine(): array
    {
        return [
            'id' => $this->id['routine'],
            'name' => 'Morning Mobility',
            'description' => 'Ten minutes a day.',
            'cover_image' => null,
            'is_active' => true,
            'type' => 'routine',
            'workout_templates' => [[
                'id' => $this->id['routine_workout'],
                'plan_id' => $this->id['routine'],
                'name' => 'Daily Flow',
                'description' => 'Loosen up',
                'day_of_week' => 2,
                'week_number' => 1,
                'order_index' => 0,
                'last_completed_session_id' => null,
                'exercises' => [[
                    'id' => $this->id['plank'],
                    'name' => 'Plank',
                    'description' => 'Brace',
                    'image' => null,
                    'video' => null,
                    'muscle_group_image' => null,
                    'default_rest_sec' => 60,
                    'category' => null,
                    'muscle_groups' => [],
                    'pivot' => [
                        'id' => $this->id['routine_row'],
                        'order' => 0,
                        'target_sets' => 3,
                        'min_target_reps' => 30,
                        'max_target_reps' => 60,
                        'target_weight' => 0,
                        'rest_seconds' => 45,
                    ],
                ]],
                'created_at' => $this->ts,
                'updated_at' => $this->ts,
            ]],
            'created_at' => $this->ts,
            'updated_at' => $this->ts,
        ];
    }
}
