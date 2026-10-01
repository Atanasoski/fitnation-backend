<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MuscleGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_muscle_groups(): void
    {
        $user = User::factory()->entitled()->create();

        MuscleGroup::factory()->count(5)->create();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/muscle-groups');

        $response
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'body_region', 'created_at', 'updated_at'],
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_list_muscle_groups(): void
    {
        MuscleGroup::factory()->count(3)->create();

        $response = $this->getJson('/api/muscle-groups');

        $response->assertUnauthorized();
    }

    public function test_muscle_groups_can_be_filtered_by_body_region(): void
    {
        $user = User::factory()->entitled()->create();

        MuscleGroup::factory()->upperBody()->count(3)->create();
        MuscleGroup::factory()->lowerBody()->count(2)->create();
        MuscleGroup::factory()->core()->count(1)->create();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/muscle-groups?body_region=upper');

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data');

        foreach ($response->json('data') as $muscleGroup) {
            $this->assertEquals('upper', $muscleGroup['body_region']);
        }
    }

    public function test_authenticated_user_can_view_single_muscle_group(): void
    {
        $user = User::factory()->entitled()->create();

        $muscleGroup = MuscleGroup::factory()->create([
            'name' => 'Chest',
            'body_region' => 'upper',
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson("/api/muscle-groups/{$muscleGroup->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $muscleGroup->id)
            ->assertJsonPath('data.name', 'Chest')
            ->assertJsonPath('data.body_region', 'upper');
    }

    public function test_muscle_group_model_has_upper_body_scope(): void
    {
        MuscleGroup::factory()->upperBody()->count(2)->create();
        MuscleGroup::factory()->lowerBody()->count(3)->create();

        $upperBodyGroups = MuscleGroup::upperBody()->get();

        $this->assertCount(2, $upperBodyGroups);
        $upperBodyGroups->each(function ($group) {
            $this->assertEquals('upper', $group->body_region);
        });
    }

    public function test_muscle_group_model_has_lower_body_scope(): void
    {
        MuscleGroup::factory()->upperBody()->count(2)->create();
        MuscleGroup::factory()->lowerBody()->count(3)->create();

        $lowerBodyGroups = MuscleGroup::lowerBody()->get();

        $this->assertCount(3, $lowerBodyGroups);
        $lowerBodyGroups->each(function ($group) {
            $this->assertEquals('lower', $group->body_region);
        });
    }

    public function test_muscle_group_model_has_core_scope(): void
    {
        MuscleGroup::factory()->core()->count(2)->create();
        MuscleGroup::factory()->upperBody()->count(1)->create();

        $coreGroups = MuscleGroup::core()->get();

        $this->assertCount(2, $coreGroups);
        $coreGroups->each(function ($group) {
            $this->assertEquals('core', $group->body_region);
        });
    }

    public function test_exercise_can_have_primary_and_secondary_muscle_groups(): void
    {
        $user = User::factory()->entitled()->create();

        $exercise = Exercise::factory()->create();
        $primaryMuscle = MuscleGroup::factory()->create(['name' => 'Chest']);
        $secondaryMuscle1 = MuscleGroup::factory()->create(['name' => 'Triceps']);
        $secondaryMuscle2 = MuscleGroup::factory()->create(['name' => 'Front Delts']);

        $exercise->muscleGroups()->attach([
            $primaryMuscle->id => ['is_primary' => true],
            $secondaryMuscle1->id => ['is_primary' => false],
            $secondaryMuscle2->id => ['is_primary' => false],
        ]);

        $this->assertCount(3, $exercise->muscleGroups);
        $this->assertCount(1, $exercise->primaryMuscleGroups);
        $this->assertCount(2, $exercise->secondaryMuscleGroups);

        $this->assertEquals('Chest', $exercise->primaryMuscleGroups->first()->name);
        $this->assertTrue($exercise->secondaryMuscleGroups->contains('name', 'Triceps'));
        $this->assertTrue($exercise->secondaryMuscleGroups->contains('name', 'Front Delts'));
    }

    public function test_muscle_group_can_have_exercises(): void
    {
        $muscleGroup = MuscleGroup::factory()->create(['name' => 'Chest']);

        $exercise1 = Exercise::factory()->create(['name' => 'Bench Press']);
        $exercise2 = Exercise::factory()->create(['name' => 'Dumbbell Flyes']);

        $muscleGroup->exercises()->attach([
            $exercise1->id => ['is_primary' => true],
            $exercise2->id => ['is_primary' => true],
        ]);

        $this->assertCount(2, $muscleGroup->exercises);
        $this->assertTrue($muscleGroup->exercises->contains('name', 'Bench Press'));
        $this->assertTrue($muscleGroup->exercises->contains('name', 'Dumbbell Flyes'));
    }
}
