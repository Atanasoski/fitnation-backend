<?php

namespace Tests\Feature;

use App\Enums\PlanType;
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
 * Locks the plan outline as a super admin uses it on an app user's plan,
 * before spec 025 deletes the partner-admin pages around it. Most outline
 * tests act as a partner admin; these repeat every route the outline uses —
 * the plan pages and the workout and row writes it posts to — as the only
 * role that will still reach them.
 */
class SuperAdminPlanOutlineCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private Partner $gym;

    private User $member;

    private User $admin;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Partner::factory()->create();
        $this->member = User::factory()->create(['partner_id' => $this->gym->id]);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id);
        $this->plan = Plan::factory()->program()->create([
            'user_id' => $this->member->id, 'partner_id' => null, 'name' => 'Strength Block', 'is_active' => false,
        ]);
    }

    public function test_the_outline_renders_with_the_back_link_to_the_admin_member_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('plans.index', ['user' => $this->member, 'plan' => $this->plan->id]))
            ->assertOk()
            ->assertSee('Strength Block')
            ->assertSee(route('admin.users.show', $this->member), false)
            ->assertSee(route('plans.update', $this->plan), false);
    }

    public function test_the_old_plan_pages_redirect_into_the_outline(): void
    {
        $this->actingAs($this->admin)
            ->get(route('plans.create', $this->member))
            ->assertRedirect(route('plans.index', ['user' => $this->member, 'create' => 'program']));

        $this->actingAs($this->admin)->get(route('plans.show', $this->plan))->assertRedirect($this->outline([]));
        $this->actingAs($this->admin)->get(route('plans.edit', $this->plan))->assertRedirect($this->outline([]));
    }

    public function test_store_creates_an_inactive_plan_and_opens_it(): void
    {
        $this->actingAs($this->admin)
            ->post(route('plans.store', $this->member), ['name' => 'Morning Mobility', 'type' => 'routine', 'is_active' => true])
            ->assertRedirect(route('plans.index', ['user' => $this->member, 'plan' => $routine = $this->planNamed('Morning Mobility')]))
            ->assertSessionHas('success', 'Routine created.');

        $this->assertSame(PlanType::Routine, $routine->type);
        $this->assertSame($this->member->id, $routine->user_id);
        $this->assertNull($routine->partner_id);
        $this->assertFalse($routine->is_active);
    }

    public function test_update_saves_and_reopens_the_plan(): void
    {
        $this->actingAs($this->admin)
            ->put(route('plans.update', $this->plan), [
                'name' => 'Renamed', 'type' => 'program', 'duration_weeks' => 10, 'description' => 'New words',
            ])
            ->assertRedirect($this->outline([]))
            ->assertSessionHas('success', 'Plan saved.');

        $this->plan->refresh();
        $this->assertSame(['Renamed', 10, 'New words', false], [$this->plan->name, $this->plan->duration_weeks, $this->plan->description, $this->plan->is_active]);
    }

    public function test_activate_makes_the_plan_active_and_reopens_it(): void
    {
        $this->actingAs($this->admin)
            ->post(route('plans.activate', $this->plan))
            ->assertRedirect($this->outline([]))
            ->assertSessionHas('success', 'Strength Block is now the active program.');

        $this->assertTrue($this->plan->fresh()->is_active);
    }

    public function test_destroy_deletes_the_plan_and_returns_to_the_outline(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('plans.destroy', $this->plan))
            ->assertRedirect(route('plans.index', $this->member))
            ->assertSessionHas('success', 'Strength Block deleted. Logged sessions are kept.');

        $this->assertModelMissing($this->plan);
    }

    public function test_the_workout_pages_redirect_into_the_outline(): void
    {
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $this->plan->id]);
        $row = $this->row($workout);

        $this->actingAs($this->admin)->get(route('workouts.create', $this->plan))
            ->assertRedirect($this->outline(['add' => 'workout']));
        $this->actingAs($this->admin)->get(route('workouts.show', $workout))
            ->assertRedirect($this->outline(['workout' => $workout]));
        $this->actingAs($this->admin)->get(route('workouts.edit', $workout))
            ->assertRedirect($this->outline(['workout' => $workout]));
        $this->actingAs($this->admin)->get(route('workout-exercises.create', $workout))
            ->assertRedirect($this->outline(['workout' => $workout, 'add' => 'exercise']));
        $this->actingAs($this->admin)->get(route('workout-exercises.edit', [$workout, $row]))
            ->assertRedirect($this->outline(['workout' => $workout, 'row' => $row]));
    }

    public function test_workout_store_update_and_destroy(): void
    {
        $this->actingAs($this->admin)
            ->post(route('workouts.store', $this->plan), ['plan_id' => $this->plan->id, 'name' => 'Push', 'day_of_week' => 2])
            ->assertRedirect($this->outline(['workout' => $workout = $this->plan->workoutTemplates()->sole()]))
            ->assertSessionHas('success', 'Workout added.');
        $this->assertSame(['Push', 2, 1, 0], [$workout->name, $workout->day_of_week, $workout->week_number, $workout->order_index]);

        $this->actingAs($this->admin)
            ->put(route('workouts.update', $workout), ['name' => 'Push A', 'day_of_week' => ''])
            ->assertRedirect($this->outline(['workout' => $workout]))
            ->assertSessionHas('success', 'Workout saved.');
        $this->assertSame(['Push A', null], [$workout->fresh()->name, $workout->fresh()->day_of_week]);

        $this->actingAs($this->admin)
            ->delete(route('workouts.destroy', $workout))
            ->assertRedirect($this->outline([]))
            ->assertSessionHas('success', 'Push A removed.');
        $this->assertModelMissing($workout);
    }

    public function test_row_store_update_swap_move_and_destroy(): void
    {
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $this->plan->id]);
        $first = $this->row($workout);
        $squat = $this->exercise('Back Squat');
        $front = $this->exercise('Front Squat');

        $this->actingAs($this->admin)
            ->post(route('workout-exercises.store', $workout), ['exercise_id' => $squat->id])
            ->assertRedirect($this->outline(['workout' => $workout, 'row' => $row = $workout->workoutTemplateExercises()->where('exercise_id', $squat->id)->sole()]))
            ->assertSessionHas('success', 'Exercise added.');
        $this->assertSame([$first->id, $row->id], $this->order($workout));
        $this->assertSame([3, 8, 12, 120], [$row->target_sets, $row->min_target_reps, $row->max_target_reps, $row->rest_seconds]);

        $this->actingAs($this->admin)
            ->put(route('workout-exercises.update', [$workout, $row]), [
                'target_sets' => 5, 'min_target_reps' => 3, 'max_target_reps' => 5, 'target_weight' => 102.5, 'rest_seconds' => 180,
            ])
            ->assertRedirect($this->outline(['workout' => $workout, 'row' => $row]))
            ->assertSessionHas('success');
        $row->refresh();
        $this->assertSame([5, 3, 5, 180], [$row->target_sets, $row->min_target_reps, $row->max_target_reps, $row->rest_seconds]);
        $this->assertEquals(102.5, $row->target_weight);

        $this->actingAs($this->admin)
            ->put(route('workout-exercises.swap', [$workout, $row]), ['exercise_id' => $front->id])
            ->assertRedirect($this->outline(['workout' => $workout, 'row' => $row]))
            ->assertSessionHas('success', 'Exercise swapped.');
        $this->assertSame($front->id, $row->fresh()->exercise_id);
        $this->assertSame(5, $row->fresh()->target_sets);

        $this->actingAs($this->admin)
            ->post(route('workout-exercises.move', [$workout, $row]), ['direction' => 'up'])
            ->assertRedirect($this->outline(['workout' => $workout]));
        $this->assertSame([$row->id, $first->id], $this->order($workout));

        $this->actingAs($this->admin)
            ->delete(route('workout-exercises.destroy', [$workout, $row]))
            ->assertRedirect($this->outline(['workout' => $workout]))
            ->assertSessionHas('success', 'Exercise removed.');
        $this->assertModelMissing($row);
        $this->assertSame([$first->id], $this->order($workout));
        $this->assertSame([0], $workout->workoutTemplateExercises()->pluck('order')->all());
    }

    private function planNamed(string $name): Plan
    {
        return Plan::where('user_id', $this->member->id)->where('name', $name)->sole();
    }

    private function exercise(string $name): Exercise
    {
        $exercise = Exercise::factory()->create(['name' => $name]);
        $exercise->partners()->attach($this->gym);

        return $exercise;
    }

    private function row(WorkoutTemplate $workout): WorkoutTemplateExercise
    {
        return WorkoutTemplateExercise::create([
            'workout_template_id' => $workout->id,
            'exercise_id' => $this->exercise('Exercise '.uniqid())->id,
            'order' => $workout->workoutTemplateExercises()->count(),
            'target_sets' => 3,
            'min_target_reps' => 8,
            'max_target_reps' => 12,
            'target_weight' => 0,
            'rest_seconds' => 120,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function order(WorkoutTemplate $workout): array
    {
        return $workout->workoutTemplateExercises()->orderBy('order')->orderBy('id')->pluck('id')->all();
    }

    /**
     * @param  array<string, WorkoutTemplate|WorkoutTemplateExercise|string>  $nodes
     */
    private function outline(array $nodes): string
    {
        $query = ['user' => $this->member->id, 'plan' => $this->plan->id];
        foreach ($nodes as $key => $node) {
            $query[$key] = is_object($node) ? $node->id : $node;
        }

        return route('plans.index', $query);
    }
}
