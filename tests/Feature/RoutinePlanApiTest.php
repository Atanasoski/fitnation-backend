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

class RoutinePlanApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_partner_routines(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        Sanctum::actingAs($user);

        // Create partner-owned routine plans (is_active so they appear)
        Plan::factory()->partnerRoutine($partner)->count(3)->create(['is_active' => true]);

        // Create routine for another partner (should not appear)
        $otherPartner = Partner::factory()->create();
        Plan::factory()->partnerRoutine($otherPartner)->create(['is_active' => true]);

        // Create user-owned routine (should not appear)
        Plan::factory()->create([
            'user_id' => $user->id,
            'partner_id' => null,
            'type' => PlanType::Routine,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/routines');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_view_single_routine(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        Sanctum::actingAs($user);

        $routine = Plan::factory()->partnerRoutine($partner)->create([
            'name' => 'Strength & Conditioning',
            'is_active' => true,
        ]);

        WorkoutTemplate::factory()->create([
            'plan_id' => $routine->id,
            'day_of_week' => 0,
        ]);

        $response = $this->getJson("/api/routines/{$routine->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $routine->id,
                    'name' => 'Strength & Conditioning',
                    'type' => 'routine',
                ],
            ]);
    }

    public function test_user_without_partner_sees_empty_routines(): void
    {
        $user = User::factory()->create(['partner_id' => null]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/routines');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_routines_excludes_inactive_plans(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        Sanctum::actingAs($user);

        Plan::factory()->partnerRoutine($partner)->create(['is_active' => true, 'name' => 'Active Routine']);
        Plan::factory()->partnerRoutine($partner)->create(['is_active' => false, 'name' => 'Inactive Routine']);

        $response = $this->getJson('/api/routines');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active Routine');
    }

    public function test_cannot_access_routine_from_different_partner(): void
    {
        $partner1 = Partner::factory()->create();
        $partner2 = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner1->id]);
        Sanctum::actingAs($user);

        $routine = Plan::factory()->partnerRoutine($partner2)->create();

        $response = $this->getJson("/api/routines/{$routine->id}");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Unauthorized',
            ]);
    }

    public function test_cannot_access_program_as_routine(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        Sanctum::actingAs($user);

        $program = Plan::factory()->partnerLibrary($partner)->create([
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/routines/{$program->id}");

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Not a browsable routine',
            ]);
    }

    public function test_cannot_access_user_owned_routine_as_browsable(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        Sanctum::actingAs($user);

        $userRoutine = Plan::factory()->create([
            'user_id' => $user->id,
            'partner_id' => null,
            'type' => PlanType::Routine,
        ]);

        $response = $this->getJson("/api/routines/{$userRoutine->id}");

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Not a browsable routine',
            ]);
    }
}
