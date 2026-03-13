<?php

namespace Tests\Feature;

use App\Enums\PlanType;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\User;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProgramApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_own_programs(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create programs for this user
        Plan::factory()->program()->count(2)->create([
            'user_id' => $user->id,
        ]);

        // Create a routine (should not appear)
        Plan::factory()->create([
            'user_id' => $user->id,
            'type' => PlanType::Routine,
        ]);

        // Create another user's program (should not appear)
        $otherUser = User::factory()->create();
        Plan::factory()->program()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/programs');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_view_library_programs(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        Sanctum::actingAs($user);

        // Create library programs for this partner (is_active so they appear in library)
        Plan::factory()->partnerLibrary($partner)->count(3)->create(['is_active' => true]);

        // Create library program for another partner (should not appear)
        $otherPartner = Partner::factory()->create();
        Plan::factory()->partnerLibrary($otherPartner)->create(['is_active' => true]);

        $response = $this->getJson('/api/programs/library');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_clone_library_program(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        Sanctum::actingAs($user);

        $libraryProgram = Plan::factory()->partnerLibrary($partner)->create([
            'name' => 'Library Program',
            'duration_weeks' => 8,
            'is_active' => true,
        ]);

        WorkoutTemplate::factory()->create([
            'plan_id' => $libraryProgram->id,
            'week_number' => 1,
            'order_index' => 0,
        ]);

        $response = $this->postJson("/api/programs/{$libraryProgram->id}/clone");

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Program cloned successfully',
                'data' => [
                    'name' => 'Library Program',
                    'duration_weeks' => 8,
                ],
            ]);

        // Verify the cloned program is owned by the user
        $clonedProgramId = $response->json('data.id');
        $this->assertDatabaseHas('plans', [
            'id' => $clonedProgramId,
            'user_id' => $user->id,
            'partner_id' => null,
            'type' => PlanType::Program->value,
        ]);
    }

    public function test_user_cannot_create_program_directly(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // The routines endpoint only creates routines
        $response = $this->postJson('/api/custom-plans', [
            'name' => 'Program',
            'type' => 'program', // This field is not accepted
            'duration_weeks' => 8,
        ]);

        // It will create a routine, not a program
        $response->assertStatus(201);
        $this->assertDatabaseHas('plans', [
            'name' => 'Program',
            'type' => PlanType::Custom->value,
        ]);
    }

    public function test_user_can_only_toggle_is_active(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $program = Plan::factory()->program()->create([
            'user_id' => $user->id,
            'name' => 'Original Name',
            'is_active' => false,
        ]);

        $response = $this->patchJson("/api/programs/{$program->id}", [
            'is_active' => true,
            'name' => 'Attempted Name Change', // Should be ignored
        ]);

        $response->assertStatus(200);

        $program->refresh();
        $this->assertTrue($program->is_active);
        $this->assertEquals('Original Name', $program->name); // Name unchanged
    }

    public function test_cannot_clone_non_library_program(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        Sanctum::actingAs($user2);

        // User-owned program (not a library plan)
        $userProgram = Plan::factory()->program()->create([
            'user_id' => $user1->id,
        ]);

        $response = $this->postJson("/api/programs/{$userProgram->id}/clone");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Only library programs can be cloned',
            ]);
    }

    public function test_user_cannot_clone_program_from_different_partner(): void
    {
        $partner1 = Partner::factory()->create();
        $partner2 = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner1->id]);
        Sanctum::actingAs($user);

        $libraryProgram = Plan::factory()->partnerLibrary($partner2)->create();

        $response = $this->postJson("/api/programs/{$libraryProgram->id}/clone");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Unauthorized',
            ]);
    }

    public function test_user_without_partner_sees_empty_library(): void
    {
        $user = User::factory()->create(['partner_id' => null]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/programs/library');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_library_excludes_inactive_programs(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        Sanctum::actingAs($user);

        Plan::factory()->partnerLibrary($partner)->create(['is_active' => true, 'name' => 'Active Program']);
        Plan::factory()->partnerLibrary($partner)->create(['is_active' => false, 'name' => 'Inactive Program']);

        $response = $this->getJson('/api/programs/library');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active Program');
    }

    public function test_cannot_access_custom_plan_as_program(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $customPlan = Plan::factory()->create([
            'user_id' => $user->id,
            'type' => PlanType::Custom,
        ]);

        $response = $this->getJson("/api/programs/{$customPlan->id}");

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Not a program',
            ]);
    }
}
