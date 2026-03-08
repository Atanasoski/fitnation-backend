<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkoutSplit;
use Database\Seeders\WorkoutSplitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WorkoutSplitSeeder::class);
    }

    public function test_index_page_requires_admin_role(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['slug' => 'user'], ['name' => 'User', 'description' => 'Regular user']));

        $response = $this->actingAs($user)->get(route('workout-preview.index'));

        $response->assertStatus(403);
    }

    public function test_index_page_renders_for_admin(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'description' => 'System admin']));

        $response = $this->actingAs($admin)->get(route('workout-preview.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.workout-preview.index');
        $response->assertSee('Workout Generator Preview');
    }

    public function test_preview_requires_admin_role(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['slug' => 'user'], ['name' => 'User', 'description' => 'Regular user']));

        $response = $this->actingAs($user)->post(route('workout-preview.preview'), [
            'fitness_goal' => 'general_fitness',
            'training_experience' => 'beginner',
            'gender' => 'male',
            'training_days_per_week' => 3,
            'duration_minutes' => 60,
            'weeks' => 1,
        ]);

        $response->assertStatus(403);
    }

    public function test_preview_validates_required_fields(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'description' => 'System admin']));

        $response = $this->actingAs($admin)->post(route('workout-preview.preview'), []);

        $response->assertSessionHasErrors(['fitness_goal', 'training_experience', 'gender', 'training_days_per_week', 'duration_minutes', 'weeks']);
    }

    public function test_preview_generates_workouts_for_valid_input(): void
    {
        // Create exercises with proper relationships for different target regions
        Exercise::factory()->press()->barbell()->flat()->count(5)->create();
        Exercise::factory()->row()->barbell()->horizontal()->count(5)->create();
        Exercise::factory()->count(10)->create([
            'target_region_id' => \App\Models\TargetRegion::firstOrCreate(
                ['code' => 'LOWER'],
                ['name' => 'Lower', 'display_order' => 30]
            )->id,
        ]);

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'description' => 'System admin']));

        $response = $this->actingAs($admin)->post(route('workout-preview.preview'), [
            'fitness_goal' => 'general_fitness',
            'training_experience' => 'beginner',
            'gender' => 'male',
            'training_days_per_week' => 3,
            'duration_minutes' => 60,
            'weeks' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('admin.workout-preview.index');
        $response->assertViewHas('weeks');
        $response->assertViewHas('summary');

        $weeks = $response->viewData('weeks');
        $this->assertIsArray($weeks);
        $this->assertCount(1, $weeks); // 1 week

        // Check that week 1 has workouts (should match training_days_per_week)
        $this->assertArrayHasKey(1, $weeks);
        $this->assertCount(3, $weeks[1]); // 3 days per week

        // Check summary
        $summary = $response->viewData('summary');
        $this->assertArrayHasKey('total_workouts', $summary);
        $this->assertArrayHasKey('unique_exercises', $summary);
        $this->assertArrayHasKey('avg_exercises_per_session', $summary);
        $this->assertEquals(3, $summary['total_workouts']); // 1 week × 3 days
    }

    public function test_preview_handles_multiple_weeks(): void
    {
        // Create exercises with proper relationships for different target regions
        Exercise::factory()->press()->barbell()->flat()->count(8)->create();
        Exercise::factory()->row()->barbell()->horizontal()->count(8)->create();
        Exercise::factory()->count(14)->create([
            'target_region_id' => \App\Models\TargetRegion::firstOrCreate(
                ['code' => 'LOWER'],
                ['name' => 'Lower', 'display_order' => 30]
            )->id,
        ]);

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'description' => 'System admin']));

        $response = $this->actingAs($admin)->post(route('workout-preview.preview'), [
            'fitness_goal' => 'muscle_gain',
            'training_experience' => 'intermediate',
            'gender' => 'female',
            'training_days_per_week' => 4,
            'duration_minutes' => 75,
            'weeks' => 2,
        ]);

        $response->assertStatus(200);
        $weeks = $response->viewData('weeks');
        $this->assertCount(2, $weeks); // 2 weeks

        // Each week should have 4 days
        foreach ($weeks as $weekNum => $days) {
            $this->assertCount(4, $days);
        }

        $summary = $response->viewData('summary');
        $this->assertEquals(8, $summary['total_workouts']); // 2 weeks × 4 days
    }

    public function test_preview_returns_error_when_split_not_found(): void
    {
        // Delete all splits to simulate missing split
        WorkoutSplit::query()->delete();

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'description' => 'System admin']));

        $response = $this->actingAs($admin)->post(route('workout-preview.preview'), [
            'fitness_goal' => 'general_fitness',
            'training_experience' => 'beginner',
            'gender' => 'male',
            'training_days_per_week' => 3,
            'duration_minutes' => 60,
            'weeks' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertViewHas('error');
    }
}
