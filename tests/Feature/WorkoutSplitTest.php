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
        // Upper / Legs / Upper, as the live catalog has it (#146) — not
        // three Full Body days, which is what the seeder used to claim.
        $this->assertEquals(['UPPER_PUSH', 'UPPER_PULL'], $split[0]);
        $this->assertEquals(['LOWER'], $split[1]);
        $this->assertEquals(['UPPER_PUSH', 'UPPER_PULL'], $split[2]);
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
        // The seeder provides every focus for 1-7 days, so drop the one
        // combination this member asks for to reach the guard.
        WorkoutSplit::query()
            ->where('days_per_week', 3)
            ->where('focus', SplitFocus::UpperFocus)
            ->delete();

        $user = User::factory()->create();
        $user->profile()->update([
            'training_days_per_week' => 3,
            'split_focus' => SplitFocus::UpperFocus,
        ]);

        $service = new WelcomePlanGenerationService(
            app(\App\Services\WorkoutGenerator\BlueprintWorkoutGenerator::class)
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No workout split found for 3 days/week with upper_focus focus');

        $service->generateWelcomePlan($user);
    }

    public function test_service_builds_from_the_split_focus_the_member_chose(): void
    {
        $this->assertNotEmpty(WorkoutSplit::getSplit(3, SplitFocus::UpperFocus));

        $user = $this->memberTrainingThreeDays(SplitFocus::UpperFocus);

        $plan = (new WelcomePlanGenerationService($this->stubPlanGenerator()))
            ->generateWelcomePlan($user);

        $this->assertNotNull($plan);
        $this->assertStringContainsString('Upper Body focus', $plan->description);
    }

    /**
     * A member who has never chosen trains Balanced — and gender has no
     * say in it any more (ADR-0013). SplitFocusTest holds the regression
     * guard that two members differing only in gender are given the same
     * Program; this one covers the fallback itself.
     */
    public function test_service_falls_back_to_balanced_when_the_member_has_chosen_nothing(): void
    {
        $user = $this->memberTrainingThreeDays(null, Gender::Female);

        $plan = (new WelcomePlanGenerationService($this->stubPlanGenerator()))
            ->generateWelcomePlan($user);

        $this->assertNotNull($plan);
        $this->assertStringContainsString('Balanced focus', $plan->description);
    }

    private function memberTrainingThreeDays(?SplitFocus $focus, Gender $gender = Gender::Male): User
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'training_days_per_week' => 3,
            'split_focus' => $focus,
            'gender' => $gender,
            'fitness_goal' => \App\Enums\FitnessGoal::MuscleGain,
            'training_experience' => \App\Enums\TrainingExperience::Beginner,
            'workout_duration_minutes' => 60,
        ]);

        return $user->fresh();
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
