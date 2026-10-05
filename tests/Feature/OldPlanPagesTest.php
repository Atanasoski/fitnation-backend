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
 * A library plan keeps its own pages.
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

    public function test_a_member_plans_old_pages_render(): void
    {
        [$plan, $workout, $row] = $this->tree(Plan::factory()->create(['user_id' => $this->member->id]));

        $this->actingAs($this->partnerAdmin);
        $this->get(route('plans.create', $this->member))->assertOk();
        $this->get(route('plans.show', $plan))->assertOk();
        $this->get(route('plans.edit', $plan))->assertOk();
        $this->get(route('workouts.create', $plan))->assertOk();
        $this->get(route('workouts.show', $workout))->assertOk();
        $this->get(route('workouts.edit', $workout))->assertOk();
        $this->get(route('workout-exercises.edit', [$workout, $row]))->assertOk();
    }

    public function test_a_library_plan_keeps_its_own_pages(): void
    {
        [$plan, $workout, $row] = $this->tree(Plan::factory()->partnerLibrary($this->partner)->create());

        $this->actingAs($this->partnerAdmin);
        $this->get(route('plans.show', $plan))->assertRedirect(route('partner.programs.show', $plan));
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
