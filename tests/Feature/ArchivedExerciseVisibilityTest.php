<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Exercise;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use App\Services\WorkoutGenerator\ExerciseSelectorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where an Archived Exercise is and is not offered (spec 023, Seam 2): gone
 * from searching, picking and generating; still shown wherever it is already
 * used.
 */
class ArchivedExerciseVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Partner $partner;

    private Exercise $live;

    private Exercise $archived;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = Partner::factory()->create();
        $this->live = Exercise::factory()->create(['name' => 'Live Bench Press']);
        $this->archived = Exercise::factory()->create(['name' => 'Retired Bench Press', 'archived_at' => now()]);
        $this->partner->exercises()->attach([$this->live->id, $this->archived->id]);
    }

    public function test_the_api_catalogue_leaves_out_archived_exercises(): void
    {
        $ids = $this->actingAs($this->member(), 'sanctum')
            ->getJson('/api/exercises')
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$this->live->id], $ids);
    }

    public function test_the_api_search_leaves_out_archived_exercises(): void
    {
        $ids = $this->actingAs($this->member(), 'sanctum')
            ->getJson('/api/exercises?search=bench')
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$this->live->id], $ids);
    }

    public function test_the_generator_never_picks_an_archived_exercise(): void
    {
        $candidates = app(ExerciseSelectorService::class)->getAvailableExercises([], $this->partner);

        $this->assertSame([$this->live->id], $candidates->modelKeys());
    }

    public function test_a_new_partner_is_not_linked_to_archived_exercises(): void
    {
        $partner = Partner::factory()->create();

        $partner->syncDefaultExercises();

        $this->assertSame([$this->live->id], $partner->exercises()->pluck('workout_exercises.id')->all());
    }

    public function test_the_workout_exercise_picker_leaves_out_archived_exercises(): void
    {
        $plan = Plan::factory()->create(['user_id' => $this->member()->id, 'partner_id' => null]);
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $plan->id]);

        $this->actingAs($this->partnerAdmin())
            ->get(route('workouts.show', $workout))
            ->assertOk()
            ->assertSee('Live Bench Press')
            ->assertDontSee('Retired Bench Press');
    }

    public function test_the_partner_library_leaves_out_archived_exercises(): void
    {
        $category = Category::factory()->create(['type' => 'workout']);
        $this->live->update(['category_id' => $category->id]);
        $this->archived->update(['category_id' => $category->id]);

        $this->actingAs($this->partnerAdmin())
            ->get(route('partner.exercises.index'))
            ->assertOk()
            ->assertSee('Live Bench Press')
            ->assertDontSee('Retired Bench Press');
    }

    public function test_an_archived_exercise_can_still_be_read_by_id(): void
    {
        $this->actingAs($this->member(), 'sanctum')
            ->getJson("/api/exercises/{$this->archived->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $this->archived->id)
            ->assertJsonPath('data.name', 'Retired Bench Press');
    }

    public function test_a_session_still_shows_an_archived_exercise_and_its_sets(): void
    {
        $member = $this->member();
        $session = WorkoutSession::factory()->create(['user_id' => $member->id]);
        $row = WorkoutSessionExercise::create([
            'workout_session_id' => $session->id,
            'exercise_id' => $this->archived->id,
            'order' => 1,
        ]);
        SetLog::create([
            'workout_session_id' => $session->id,
            'workout_session_exercise_id' => $row->id,
            'exercise_id' => $this->archived->id,
            'set_number' => 1,
            'weight' => 60,
            'reps' => 8,
        ]);

        $exercises = $this->actingAs($member, 'sanctum')
            ->getJson("/api/workout-sessions/{$session->id}")
            ->assertOk()
            ->json('data.exercises');

        $this->assertCount(1, $exercises);
        $this->assertSame('Retired Bench Press', $exercises[0]['session_exercise']['exercise']['name'] ?? null);
        $this->assertCount(1, $exercises[0]['logged_sets']);
    }

    public function test_a_plan_workout_still_shows_an_archived_exercise(): void
    {
        $member = $this->member();
        $plan = Plan::factory()->create(['user_id' => $member->id, 'partner_id' => null]);
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $plan->id]);
        WorkoutTemplateExercise::create([
            'workout_template_id' => $workout->id,
            'exercise_id' => $this->archived->id,
            'order' => 1,
        ]);

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/workout-templates/{$workout->id}")
            ->assertOk()
            ->assertJsonPath('data.exercises.0.id', $this->archived->id);
    }

    private function member(): User
    {
        return User::factory()->entitled()->create(['partner_id' => $this->partner->id]);
    }

    private function partnerAdmin(): User
    {
        $admin = User::factory()->create(['partner_id' => $this->partner->id]);
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'partner_admin'], ['name' => 'Partner Admin'])->id);

        return $admin;
    }
}
