<?php

namespace Tests\Feature;

use App\Enums\PlanType;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_routine_plan(): void
    {
        $user = User::factory()->create();

        $plan = Plan::factory()->create([
            'user_id' => $user->id,
            'type' => PlanType::Routine,
            'name' => 'My Routine',
        ]);

        $this->assertTrue($plan->isRoutine());
        $this->assertFalse($plan->isProgram());
        $this->assertNull($plan->duration_weeks);
    }

    public function test_can_create_program_plan_with_duration(): void
    {
        $user = User::factory()->create();

        $plan = Plan::factory()->program()->create([
            'user_id' => $user->id,
            'duration_weeks' => 8,
        ]);

        $this->assertTrue($plan->isProgram());
        $this->assertFalse($plan->isRoutine());
        $this->assertEquals(8, $plan->duration_weeks);
    }

    public function test_can_update_plan_type(): void
    {
        $user = User::factory()->create();

        $plan = Plan::factory()->create([
            'user_id' => $user->id,
            'type' => PlanType::Routine,
        ]);

        $plan->update([
            'type' => PlanType::Program,
            'duration_weeks' => 12,
        ]);

        $this->assertTrue($plan->isProgram());
        $this->assertEquals(12, $plan->duration_weeks);
    }

    public function test_next_workout_returns_first_for_new_program(): void
    {
        $user = User::factory()->create();

        $plan = Plan::factory()->program()->create([
            'user_id' => $user->id,
        ]);

        $template1 = WorkoutTemplate::factory()->create([
            'plan_id' => $plan->id,
            'week_number' => 1,
            'order_index' => 0,
        ]);

        WorkoutTemplate::factory()->create([
            'plan_id' => $plan->id,
            'week_number' => 1,
            'order_index' => 1,
        ]);

        $nextWorkout = $plan->nextWorkout($user);

        $this->assertNotNull($nextWorkout);
        $this->assertEquals($template1->id, $nextWorkout->id);
    }

    public function test_next_workout_returns_next_after_completion(): void
    {
        $user = User::factory()->create();

        $plan = Plan::factory()->program()->create([
            'user_id' => $user->id,
        ]);

        $template1 = WorkoutTemplate::factory()->create([
            'plan_id' => $plan->id,
            'week_number' => 1,
            'order_index' => 0,
        ]);

        $template2 = WorkoutTemplate::factory()->create([
            'plan_id' => $plan->id,
            'week_number' => 1,
            'order_index' => 1,
        ]);

        // Complete first workout
        WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => $template1->id,
            'status' => 'completed',
        ]);

        $nextWorkout = $plan->nextWorkout($user);

        $this->assertNotNull($nextWorkout);
        $this->assertEquals($template2->id, $nextWorkout->id);
    }

    public function test_next_workout_returns_null_when_program_completed(): void
    {
        $user = User::factory()->create();

        $plan = Plan::factory()->program()->create([
            'user_id' => $user->id,
        ]);

        $template1 = WorkoutTemplate::factory()->create([
            'plan_id' => $plan->id,
            'week_number' => 1,
            'order_index' => 0,
        ]);

        // Complete all workouts
        WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => $template1->id,
            'status' => 'completed',
        ]);

        $nextWorkout = $plan->nextWorkout($user);

        $this->assertNull($nextWorkout);
    }

    public function test_progress_percentage_calculation(): void
    {
        $user = User::factory()->create();

        $plan = Plan::factory()->program()->create([
            'user_id' => $user->id,
        ]);

        $template1 = WorkoutTemplate::factory()->create([
            'plan_id' => $plan->id,
            'week_number' => 1,
            'order_index' => 0,
        ]);

        $template2 = WorkoutTemplate::factory()->create([
            'plan_id' => $plan->id,
            'week_number' => 1,
            'order_index' => 1,
        ]);

        // No workouts completed - 0%
        $this->assertEquals(0, $plan->getProgressPercentage($user));

        // Complete first workout - 50%
        WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => $template1->id,
            'status' => 'completed',
        ]);

        $this->assertEquals(50, $plan->getProgressPercentage($user));

        // Complete second workout - 100%
        WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => $template2->id,
            'status' => 'completed',
        ]);

        $this->assertEquals(100, $plan->getProgressPercentage($user));
    }

    public function test_partner_library_plan_identification(): void
    {
        $partner = Partner::factory()->create();

        $libraryPlan = Plan::factory()->partnerLibrary($partner)->create();

        $this->assertTrue($libraryPlan->isPartnerLibraryPlan());
        $this->assertTrue($libraryPlan->isPartnerProvided());
        $this->assertNull($libraryPlan->user_id);
        $this->assertEquals($partner->id, $libraryPlan->partner_id);
        $this->assertEquals(PlanType::Program, $libraryPlan->type);
    }
}
