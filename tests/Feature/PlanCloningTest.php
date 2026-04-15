<?php

namespace Tests\Feature;

use App\Enums\PlanType;
use App\Models\Exercise;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\User;
use App\Models\WorkoutTemplate;
use App\Services\PlanCloningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanCloningTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_clone_program_from_library(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);

        $libraryPlan = Plan::factory()->partnerLibrary($partner)->create([
            'name' => 'Library Program',
        ]);

        $service = new PlanCloningService;
        $clonedPlan = $service->clone($libraryPlan, $user);

        $this->assertNotEquals($libraryPlan->id, $clonedPlan->id);
        $this->assertEquals($user->id, $clonedPlan->user_id);
        $this->assertNull($clonedPlan->partner_id);
        $this->assertEquals('Library Program', $clonedPlan->name);
    }

    public function test_cloning_copies_all_templates_and_exercises(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);

        $libraryPlan = Plan::factory()->partnerLibrary($partner)->create();

        $template1 = WorkoutTemplate::factory()->create([
            'plan_id' => $libraryPlan->id,
            'name' => 'Day 1',
            'week_number' => 1,
            'order_index' => 0,
        ]);

        $template2 = WorkoutTemplate::factory()->create([
            'plan_id' => $libraryPlan->id,
            'name' => 'Day 2',
            'week_number' => 1,
            'order_index' => 1,
        ]);

        $exercise1 = Exercise::factory()->create();
        $exercise2 = Exercise::factory()->create();

        $template1->exercises()->attach($exercise1->id, [
            'order' => 1,
            'target_sets' => 3,
            'min_target_reps' => 8,
            'max_target_reps' => 10,
        ]);

        $template2->exercises()->attach($exercise2->id, [
            'order' => 1,
            'target_sets' => 4,
            'min_target_reps' => 10,
            'max_target_reps' => 12,
        ]);

        $service = new PlanCloningService;
        $clonedPlan = $service->clone($libraryPlan, $user);

        // Verify templates were cloned
        $this->assertEquals(2, $clonedPlan->workoutTemplates->count());

        $clonedTemplate1 = $clonedPlan->workoutTemplates->where('name', 'Day 1')->first();
        $clonedTemplate2 = $clonedPlan->workoutTemplates->where('name', 'Day 2')->first();

        $this->assertNotNull($clonedTemplate1);
        $this->assertNotNull($clonedTemplate2);
        $this->assertNotEquals($template1->id, $clonedTemplate1->id);
        $this->assertNotEquals($template2->id, $clonedTemplate2->id);

        // Verify exercises were cloned
        $this->assertEquals(1, $clonedTemplate1->workoutTemplateExercises->count());
        $this->assertEquals(1, $clonedTemplate2->workoutTemplateExercises->count());

        // Verify exercise pivot data was preserved
        $clonedExercise1 = $clonedTemplate1->workoutTemplateExercises->first();
        $this->assertEquals($exercise1->id, $clonedExercise1->exercise_id);
        $this->assertEquals(3, $clonedExercise1->target_sets);
        $this->assertEquals(8, $clonedExercise1->min_target_reps);
        $this->assertEquals(10, $clonedExercise1->max_target_reps);
    }

    public function test_cloning_partner_plan_clears_partner_id(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);

        $libraryPlan = Plan::factory()->partnerLibrary($partner)->create();

        $this->assertEquals($partner->id, $libraryPlan->partner_id);

        $service = new PlanCloningService;
        $clonedPlan = $service->clone($libraryPlan, $user);

        $this->assertNull($clonedPlan->partner_id);
        $this->assertFalse($clonedPlan->isPartnerLibraryPlan());
    }

    public function test_cloning_sets_user_id_to_target_user(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);

        $libraryPlan = Plan::factory()->partnerLibrary($partner)->create();

        $this->assertNull($libraryPlan->user_id);

        $service = new PlanCloningService;
        $clonedPlan = $service->clone($libraryPlan, $user);

        $this->assertEquals($user->id, $clonedPlan->user_id);
    }

    public function test_cloned_plan_is_inactive_by_default(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);

        $libraryPlan = Plan::factory()->partnerLibrary($partner)->create([
            'is_active' => true, // Library plan is active
        ]);

        $service = new PlanCloningService;
        $clonedPlan = $service->clone($libraryPlan, $user);

        $this->assertFalse($clonedPlan->is_active);
    }

    public function test_cannot_clone_non_library_plan(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User-owned plan (not a library plan)
        $userPlan = Plan::factory()->create([
            'user_id' => $user1->id,
            'type' => PlanType::Program,
        ]);

        $this->assertFalse($userPlan->isPartnerLibraryPlan());

        // Cloning service will still work, but API should prevent this
        // This test documents the expected behavior at the service level
        $service = new PlanCloningService;
        $clonedPlan = $service->clone($userPlan, $user2);

        // Service completes the clone, but API validation should prevent this
        $this->assertNotNull($clonedPlan);
    }
}
