<?php

namespace Tests\Feature;

use App\Enums\PlanType;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomPlanApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_custom_plan(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/custom-plans', [
            'name' => 'My Custom Plan',
            'description' => 'Quick morning workout',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('plans', [
            'user_id' => $user->id,
            'name' => 'My Custom Plan',
            'type' => PlanType::Routine->value,
        ]);
    }

    public function test_user_can_list_own_custom_plans(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create routines for this user
        Plan::factory()->count(2)->create([
            'user_id' => $user->id,
            'type' => PlanType::Routine,
        ]);

        // Create a program (should not appear)
        Plan::factory()->program()->create([
            'user_id' => $user->id,
        ]);

        // Create another user's routine (should not appear)
        $otherUser = User::factory()->create();
        Plan::factory()->create([
            'user_id' => $otherUser->id,
            'type' => PlanType::Routine,
        ]);

        $response = $this->getJson('/api/custom-plans');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_update_own_custom_plan(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $routine = Plan::factory()->create([
            'user_id' => $user->id,
            'type' => PlanType::Routine,
            'name' => 'Old Name',
        ]);

        $response = $this->putJson("/api/custom-plans/{$routine->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'is_active' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Custom plan updated successfully',
                'data' => [
                    'name' => 'Updated Name',
                    'description' => 'Updated description',
                    'is_active' => false,
                ],
            ]);

        $this->assertDatabaseHas('plans', [
            'id' => $routine->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_user_can_delete_own_custom_plan(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $routine = Plan::factory()->create([
            'user_id' => $user->id,
            'type' => PlanType::Routine,
        ]);

        $response = $this->deleteJson("/api/custom-plans/{$routine->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Custom plan deleted successfully',
            ]);

        $this->assertDatabaseMissing('plans', [
            'id' => $routine->id,
        ]);
    }

    public function test_user_cannot_access_other_users_routines(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        Sanctum::actingAs($user1);

        $customPlan = Plan::factory()->create([
            'user_id' => $user2->id,
            'type' => PlanType::Routine,
        ]);

        // Try to view
        $response = $this->getJson("/api/custom-plans/{$customPlan->id}");
        $response->assertStatus(403);

        // Try to update
        $response = $this->putJson("/api/custom-plans/{$customPlan->id}", [
            'name' => 'Hacked Name',
        ]);
        $response->assertStatus(403);

        // Try to delete
        $response = $this->deleteJson("/api/custom-plans/{$customPlan->id}");
        $response->assertStatus(403);
    }

    public function test_custom_plan_creation_requires_name(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/custom-plans', [
            'description' => 'No name provided',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_cannot_access_program_as_custom_plan(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $program = Plan::factory()->program()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->getJson("/api/custom-plans/{$program->id}");

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Not a custom plan',
            ]);
    }
}
