<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The plan pages that came before the plan outline (023/06): the user-plan
 * create, show and edit pages, and the workout and workout-exercise pages.
 * Each now redirects to the matching node of the outline. A library plan
 * has no web pages (spec 025).
 */
class OldPlanPagesTest extends TestCase
{
    use RefreshDatabase;

    private Partner $partner;

    private User $superAdmin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = Partner::factory()->create();
        // The plan pages are super-admin only since 025/02.
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->roles()->attach(
            Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id
        );
        $this->member = User::factory()->entitled()->create(['partner_id' => $this->partner->id]);
    }

    public function test_a_member_plans_old_pages_redirect_to_the_matching_outline_node(): void
    {
        [$plan, $workout, $row] = $this->tree(Plan::factory()->create(['user_id' => $this->member->id]));
        $outline = fn (array $query) => route('plans.index', ['user' => $this->member->id] + $query);

        $this->actingAs($this->superAdmin);
        $this->get(route('plans.create', $this->member))->assertRedirect($outline(['create' => 'program']));
        $this->get(route('plans.show', $plan))->assertRedirect($outline(['plan' => $plan->id]));
        $this->get(route('plans.edit', $plan))->assertRedirect($outline(['plan' => $plan->id]));
        $this->get(route('workouts.create', $plan))->assertRedirect($outline(['plan' => $plan->id, 'add' => 'workout']));
        $this->get(route('workouts.show', $workout))->assertRedirect($outline(['plan' => $plan->id, 'workout' => $workout->id]));
        $this->get(route('workouts.edit', $workout))->assertRedirect($outline(['plan' => $plan->id, 'workout' => $workout->id]));
        $this->get(route('workout-exercises.create', $workout))
            ->assertRedirect($outline(['plan' => $plan->id, 'workout' => $workout->id, 'add' => 'exercise']));
        $this->get(route('workout-exercises.edit', [$workout, $row]))
            ->assertRedirect($outline(['plan' => $plan->id, 'workout' => $workout->id, 'row' => $row->id]));
    }

    public function test_the_old_pages_still_check_the_plan_policy(): void
    {
        [$plan, $workout, $row] = $this->tree(Plan::factory()->create(['user_id' => $this->member->id]));
        $rival = User::factory()->create(['partner_id' => Partner::factory()->create()->id]);
        $rival->roles()->attach(Role::firstOrCreate(['slug' => 'partner_admin'], ['name' => 'Partner Admin'])->id);

        $this->actingAs($rival);
        $this->get(route('plans.show', $plan))->assertForbidden();
        $this->get(route('workouts.show', $workout))->assertForbidden();
        $this->get(route('workout-exercises.edit', [$workout, $row]))->assertForbidden();
    }

    /**
     * Library plans have no web pages since spec 025: every plan, workout and
     * workout-exercise route 404s for one, reads and writes alike, and
     * nothing changes. The API still serves them (LibraryApiCharacterizationTest).
     */
    public function test_a_library_plan_has_no_web_pages(): void
    {
        [$plan, $workout, $row] = $this->tree(Plan::factory()->partnerLibrary($this->partner)->create(['name' => 'Library', 'is_active' => true]));
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id);
        $other = Exercise::factory()->create();
        $this->partner->exercises()->attach($other->id);

        $this->actingAs($admin);
        $this->get(route('plans.show', $plan))->assertNotFound();
        $this->get(route('plans.edit', $plan))->assertNotFound();
        $this->put(route('plans.update', $plan), ['name' => 'Renamed', 'type' => 'program', 'duration_weeks' => 4])->assertNotFound();
        $this->post(route('plans.activate', $plan))->assertNotFound();
        $this->delete(route('plans.destroy', $plan))->assertNotFound();
        $this->get(route('workouts.create', $plan))->assertNotFound();
        $this->post(route('workouts.store', $plan), ['name' => 'New day'])->assertNotFound();
        $this->get(route('workouts.show', $workout))->assertNotFound();
        $this->get(route('workouts.edit', $workout))->assertNotFound();
        $this->put(route('workouts.update', $workout), ['name' => 'Renamed'])->assertNotFound();
        $this->delete(route('workouts.destroy', $workout))->assertNotFound();
        $this->get(route('workout-exercises.create', $workout))->assertNotFound();
        $this->post(route('workout-exercises.store', $workout), ['exercise_id' => $other->id])->assertNotFound();
        $this->get(route('workout-exercises.edit', [$workout, $row]))->assertNotFound();
        $this->put(route('workout-exercises.update', [$workout, $row]), ['target_sets' => 5])->assertNotFound();
        $this->put(route('workout-exercises.swap', [$workout, $row]), ['exercise_id' => $other->id])->assertNotFound();
        $this->post(route('workout-exercises.move', [$workout, $row]), ['direction' => 'down'])->assertNotFound();
        $this->delete(route('workout-exercises.destroy', [$workout, $row]))->assertNotFound();

        $this->assertSame('Library', $plan->fresh()->name);
        $this->assertSame(1, $plan->workoutTemplates()->count());
        $this->assertSame($workout->name, $workout->fresh()->name);
        $this->assertSame(3, $row->fresh()->target_sets);
        $this->assertSame($row->exercise_id, $row->fresh()->exercise_id);

        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/programs/library')
            ->assertOk()
            ->assertJsonPath('data.0.id', $plan->id);
    }

    /**
     * @return array{Plan, WorkoutTemplate, WorkoutTemplateExercise}
     */
    private function tree(Plan $plan): array
    {
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $plan->id]);
        $exercise = Exercise::factory()->create();
        $this->partner->exercises()->attach($exercise->id);
        $row = WorkoutTemplateExercise::create([
            'workout_template_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 0,
            'target_sets' => 3,
            'min_target_reps' => 8,
            'max_target_reps' => 12,
            'target_weight' => 0,
            'rest_seconds' => 120,
        ]);

        return [$plan, $workout, $row];
    }
}
