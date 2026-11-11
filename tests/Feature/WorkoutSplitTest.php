<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\SplitFocus;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkoutSplit;
use App\Services\WelcomePlanGenerationService;
use Database\Seeders\TargetRegionSeeder;
use Database\Seeders\WorkoutSplitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\StubsPlanGeneration;
use Tests\TestCase;

class WorkoutSplitTest extends TestCase
{
    use RefreshDatabase;
    use StubsPlanGeneration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WorkoutSplitSeeder::class);
    }

    public function test_can_fetch_balanced_split_for_given_days_per_week(): void
    {
        $split = WorkoutSplit::getSplit(3, SplitFocus::Balanced);

        $this->assertIsArray($split);
        $this->assertCount(3, $split);
        $this->assertEquals(['UPPER_PUSH', 'UPPER_PULL', 'LOWER'], $split[0]);
        $this->assertEquals(['UPPER_PULL', 'UPPER_PUSH', 'LOWER'], $split[1]);
        $this->assertEquals(['LOWER', 'UPPER_PUSH', 'UPPER_PULL'], $split[2]);
    }

    public function test_returns_empty_array_for_non_existent_split_combination(): void
    {
        // The seeder covers every focus for 1-7 days per week, so 8 is the
        // combination that genuinely has no split.
        $split = WorkoutSplit::getSplit(8, SplitFocus::LowerFocus);

        $this->assertIsArray($split);
        $this->assertEmpty($split);
    }

    public function test_service_throws_exception_when_split_not_found(): void
    {
        // A female user resolves to lower_focus, which the seeder now provides,
        // so drop that combination to reach the guard.
        WorkoutSplit::query()
            ->where('days_per_week', 3)
            ->where('focus', SplitFocus::LowerFocus)
            ->delete();

        $user = User::factory()->create();
        $user->profile()->update([
            'training_days_per_week' => 3,
            'gender' => Gender::Female,
        ]);

        $service = new WelcomePlanGenerationService(
            app(\App\Services\WorkoutGenerator\BlueprintWorkoutGenerator::class)
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No workout split found for 3 days/week with lower_focus focus');

        $service->generateWelcomePlan($user);
    }

    public function test_service_uses_balanced_focus_for_male_user(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'training_days_per_week' => 3,
            'gender' => Gender::Male,
            'fitness_goal' => \App\Enums\FitnessGoal::MuscleGain,
            'training_experience' => \App\Enums\TrainingExperience::Beginner,
            'workout_duration_minutes' => 60,
        ]);

        $service = new WelcomePlanGenerationService($this->stubPlanGenerator());

        // This should not throw an exception because balanced split exists
        $plan = $service->generateWelcomePlan($user);

        $this->assertNotNull($plan);
    }

    public function test_service_uses_lower_focus_for_female_user(): void
    {
        // The seeder already provides the lower_focus 3-day split this covers;
        // creating it again violates the days/focus/day_index unique key.
        $this->assertNotEmpty(WorkoutSplit::getSplit(3, SplitFocus::LowerFocus));

        $user = User::factory()->create();
        $user->profile()->update([
            'training_days_per_week' => 3,
            'gender' => Gender::Female,
            'fitness_goal' => \App\Enums\FitnessGoal::MuscleGain,
            'training_experience' => \App\Enums\TrainingExperience::Beginner,
            'workout_duration_minutes' => 60,
        ]);

        $service = new WelcomePlanGenerationService($this->stubPlanGenerator());

        // This should use lower_focus split
        $plan = $service->generateWelcomePlan($user);

        $this->assertNotNull($plan);
    }

    public function test_service_uses_balanced_focus_for_other_gender(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'training_days_per_week' => 3,
            'gender' => Gender::Other,
            'fitness_goal' => \App\Enums\FitnessGoal::MuscleGain,
            'training_experience' => \App\Enums\TrainingExperience::Beginner,
            'workout_duration_minutes' => 60,
        ]);

        $service = new WelcomePlanGenerationService($this->stubPlanGenerator());

        // This should use balanced split (default)
        $plan = $service->generateWelcomePlan($user);

        $this->assertNotNull($plan);
    }

    public function test_all_balanced_splits_are_seeded_correctly(): void
    {
        $expectedSplits = [
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
            5 => 5,
            6 => 6,
            7 => 7,
        ];

        foreach ($expectedSplits as $daysPerWeek => $expectedDayCount) {
            $split = WorkoutSplit::getSplit($daysPerWeek, SplitFocus::Balanced);
            $this->assertCount($expectedDayCount, $split, "Failed for {$daysPerWeek} days per week");
        }
    }

    public function test_the_split_form_rejects_full_body_combined_with_another_region(): void
    {
        // FULL_BODY is requested alone or not at all (ADR-0010, #127): no
        // split may ask for a day the engine cannot build.
        $this->seed(TargetRegionSeeder::class);
        $this->freeTheLastUpperFocusDay();

        $this->actingAs($this->admin())
            ->postJson('/admin/workout-splits', $this->lastUpperFocusDay(['FULL_BODY', 'ARMS']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['target_regions']);

        $this->assertNull(WorkoutSplit::getSplit(7, SplitFocus::UpperFocus)[6] ?? null);
    }

    public function test_the_split_form_rejects_an_update_combining_full_body_with_another_region(): void
    {
        $this->seed(TargetRegionSeeder::class);
        $split = WorkoutSplit::query()->firstOrFail();
        $regions = $split->target_regions;

        $this->actingAs($this->admin())
            ->putJson("/admin/workout-splits/{$split->id}", [
                'days_per_week' => $split->days_per_week,
                'focus' => $split->focus->value,
                'day_index' => $split->day_index,
                'target_regions' => ['LOWER', 'FULL_BODY'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['target_regions']);

        $this->assertSame($regions, $split->fresh()->target_regions);
    }

    public function test_the_split_form_accepts_full_body_on_its_own(): void
    {
        $this->seed(TargetRegionSeeder::class);
        $this->freeTheLastUpperFocusDay();

        $this->actingAs($this->admin())
            ->post('/admin/workout-splits', $this->lastUpperFocusDay(['FULL_BODY']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workout-splits.index'));

        $this->assertSame(['FULL_BODY'], WorkoutSplit::getSplit(7, SplitFocus::UpperFocus)[6]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id);

        return $admin;
    }

    /**
     * The seeded 7-day upper-focus split's last day, removed so the form
     * can create it again (days/focus/day_index is unique).
     */
    private function freeTheLastUpperFocusDay(): void
    {
        WorkoutSplit::query()
            ->where('days_per_week', 7)
            ->where('focus', SplitFocus::UpperFocus)
            ->where('day_index', 6)
            ->delete();
    }

    /**
     * @param  list<string>  $regions
     * @return array<string, mixed>
     */
    private function lastUpperFocusDay(array $regions): array
    {
        return [
            'days_per_week' => 7,
            'focus' => SplitFocus::UpperFocus->value,
            'day_index' => 6,
            'target_regions' => $regions,
        ];
    }
}
