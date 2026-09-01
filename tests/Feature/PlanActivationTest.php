<?php

namespace Tests\Feature;

use App\Enums\PlanType;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Services\WelcomePlanGenerationService;
use App\Services\WorkoutGenerator\DeterministicWorkoutGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * One active plan per user per type: activating a Routine may not touch the
 * user's active Program, and vice versa. See ADR-0002.
 */
class PlanActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_active_routine_leaves_the_active_program_alone(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $program = Plan::factory()->program()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $this->postJson('/api/custom-plans', [
            'name' => 'Morning Routine',
            'is_active' => true,
        ])->assertStatus(201);

        $this->assertTrue($program->fresh()->is_active, 'Creating a routine deactivated the user\'s program.');
    }

    public function test_creating_and_updating_a_routine_leave_the_same_plans_active(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $program = Plan::factory()->program()->create(['user_id' => $user->id, 'is_active' => true]);
        $otherRoutine = Plan::factory()->create([
            'user_id' => $user->id,
            'type' => PlanType::Routine,
            'is_active' => true,
        ]);
        $strangersRoutine = Plan::factory()->create(['type' => PlanType::Routine, 'is_active' => true]);

        $created = $this->postJson('/api/custom-plans', ['name' => 'Morning', 'is_active' => true])
            ->assertStatus(201);
        $afterCreate = $this->activePlanIds($user);

        $otherRoutine->update(['is_active' => true]);
        $this->putJson('/api/custom-plans/'.$created->json('data.id'), ['name' => 'Morning', 'is_active' => true])
            ->assertStatus(200);

        $this->assertSame($afterCreate, $this->activePlanIds($user));
        $this->assertSame([$program->id, $created->json('data.id')], $afterCreate);
        $this->assertTrue($strangersRoutine->fresh()->is_active, 'Another user\'s routine was deactivated.');
    }

    public function test_creating_an_active_plan_via_the_generic_endpoint_leaves_the_other_type_alone(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $program = Plan::factory()->program()->create(['user_id' => $user->id, 'is_active' => true]);
        $routine = Plan::factory()->create([
            'user_id' => $user->id,
            'type' => PlanType::Routine,
            'is_active' => true,
        ]);

        $this->postJson('/api/plans', [
            'name' => 'Generic Plan',
            'type' => PlanType::Routine->value,
            'is_active' => true,
        ])->assertStatus(201);

        $this->assertTrue($program->fresh()->is_active, 'The generic endpoint deactivated the user\'s program.');
        $this->assertFalse($routine->fresh()->is_active);
    }

    public function test_a_partner_admin_activating_a_users_program_leaves_their_routine_alone(): void
    {
        $partner = Partner::factory()->create();
        $admin = User::factory()->create(['partner_id' => $partner->id]);
        $admin->roles()->attach(Role::firstOrCreate(
            ['slug' => 'partner_admin'],
            ['name' => 'Partner Admin', 'description' => 'Can manage partner organization']
        ));
        $member = User::factory()->create(['partner_id' => $partner->id]);

        $routine = Plan::factory()->create([
            'user_id' => $member->id,
            'type' => PlanType::Routine,
            'is_active' => true,
        ]);
        $oldProgram = Plan::factory()->program()->create(['user_id' => $member->id, 'is_active' => true]);

        $this->actingAs($admin)->post(route('plans.store', $member), [
            'name' => 'New Program',
            'type' => PlanType::Program->value,
            'duration_weeks' => 4,
            'is_active' => true,
        ]);

        $created = Plan::where('name', 'New Program')->firstOrFail();

        $this->assertTrue($routine->fresh()->is_active, 'Creating a program deactivated the user\'s routine.');
        $this->assertFalse($oldProgram->fresh()->is_active);
        $this->assertSame([$routine->id, $created->id], $this->activePlanIds($member));

        // The update path must land on the same set.
        $oldProgram->update(['is_active' => true]);
        $this->actingAs($admin)->put(route('plans.update', $created), [
            'name' => 'New Program',
            'type' => PlanType::Program->value,
            'duration_weeks' => 4,
            'is_active' => true,
        ]);

        $this->assertSame([$routine->id, $created->id], $this->activePlanIds($member));
    }

    public function test_generating_a_program_deactivates_every_other_program_not_only_auto_generated_ones(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'training_days_per_week' => 3,
            'gender' => \App\Enums\Gender::Male,
            'fitness_goal' => \App\Enums\FitnessGoal::MuscleGain,
            'training_experience' => \App\Enums\TrainingExperience::Beginner,
            'workout_duration_minutes' => 60,
        ]);

        $handMadeProgram = Plan::factory()->program()->create(['user_id' => $user->id, 'is_active' => true]);
        $autoProgram = Plan::factory()->program()->autoGenerated()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);
        $routine = Plan::factory()->create([
            'user_id' => $user->id,
            'type' => PlanType::Routine,
            'is_active' => true,
        ]);

        $this->seed(\Database\Seeders\WorkoutSplitSeeder::class);

        $generator = $this->createMock(DeterministicWorkoutGenerator::class);
        $generator->method('generate')->willReturn(['exercises' => [], 'rationale' => 'Test']);

        $plan = (new WelcomePlanGenerationService($generator))->generatePlan($user);

        $this->assertTrue($plan->fresh()->is_active);
        $this->assertFalse($autoProgram->fresh()->is_active);
        $this->assertFalse(
            $handMadeProgram->fresh()->is_active,
            'A hand-made active program survived generation of a new one.'
        );
        $this->assertTrue($routine->fresh()->is_active, 'Generating a program deactivated the user\'s routine.');
        $this->assertSame([$routine->id, $plan->id], $this->activePlanIds($user));
    }

    public function test_an_update_that_omits_is_active_leaves_the_plan_active(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $routine = Plan::factory()->create([
            'user_id' => $user->id,
            'type' => PlanType::Routine,
            'is_active' => true,
        ]);

        $this->putJson('/api/custom-plans/'.$routine->id, ['name' => 'Renamed'])
            ->assertStatus(200);

        $this->assertTrue(
            $routine->fresh()->is_active,
            'Renaming a routine deactivated it.'
        );
    }

    public function test_an_active_plan_that_changes_type_does_not_leave_two_active(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $program = Plan::factory()->program()->create(['user_id' => $user->id, 'is_active' => true]);
        $routine = Plan::factory()->create([
            'user_id' => $user->id,
            'type' => PlanType::Routine,
            'is_active' => true,
        ]);

        // No is_active in the payload: the plan is already active, and the
        // rule has to be re-read against the type it just became.
        $this->putJson('/api/plans/'.$routine->id, [
            'name' => $routine->name,
            'type' => PlanType::Program->value,
        ])->assertStatus(200);

        $this->assertSame(PlanType::Program, $routine->fresh()->type);
        $this->assertSame(
            [$routine->id],
            $this->activePlanIds($user),
            'Changing an active routine into a program left two active programs.'
        );
        $this->assertFalse($program->fresh()->is_active);
    }

    /**
     * @return array<int, int>
     */
    private function activePlanIds(User $user): array
    {
        return Plan::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }
}
