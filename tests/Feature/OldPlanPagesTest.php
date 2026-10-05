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
 * keeps its own pages.
 */
class OldPlanPagesTest extends TestCase
{
    use RefreshDatabase;

    private Partner $partner;

    private User $partnerAdmin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = Partner::factory()->create();
        $this->partnerAdmin = User::factory()->create(['partner_id' => $this->partner->id]);
        $this->partnerAdmin->roles()->attach(
            Role::firstOrCreate(['slug' => 'partner_admin'], ['name' => 'Partner Admin'])->id
        );
        $this->member = User::factory()->entitled()->create(['partner_id' => $this->partner->id]);
    }

    public function test_a_member_plans_old_pages_redirect_to_the_matching_outline_node(): void
    {
        [$plan, $workout, $row] = $this->tree(Plan::factory()->create(['user_id' => $this->member->id]));
        $outline = fn (array $query) => route('plans.index', ['user' => $this->member->id] + $query);

        $this->actingAs($this->partnerAdmin);
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

    public function test_a_library_plan_keeps_its_own_pages(): void
    {
        [$plan, $workout, $row] = $this->tree(Plan::factory()->partnerLibrary($this->partner)->create());

        $this->actingAs($this->partnerAdmin);
        $this->get(route('plans.show', $plan))->assertRedirect(route('partner.programs.show', $plan));
        $this->get(route('plans.edit', $plan))->assertRedirect(route('partner.programs.edit', $plan));
        $this->get(route('workout-exercises.create', $workout))->assertRedirect(route('workouts.show', $workout));
        $this->get(route('workouts.create', $plan))->assertOk();
        $this->get(route('workouts.show', $workout))->assertOk();
        $this->get(route('workouts.edit', $workout))->assertOk();
        $this->get(route('workout-exercises.edit', [$workout, $row]))->assertOk();
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
