<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Enums\SplitFocus;
use App\Enums\TrainingExperience;
use App\Models\User;
use App\Models\WorkoutSplit;
use Database\Seeders\WorkoutSplitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCatalogExercises;
use Tests\TestCase;

/**
 * Split Focus is the member's own choice (#143): the emphasis stored on
 * their profile selects the Split their Program is generated from, and
 * gender no longer takes part — two members who answer the training
 * questions identically receive the same Program whatever their gender.
 *
 * The API seam, because that is where a member can observe it: the
 * profile endpoint stores the emphasis, the regenerate endpoint builds
 * the Program, and the generated Workout names say which Split produced
 * the week.
 */
class SplitFocusTest extends TestCase
{
    use CreatesCatalogExercises;
    use RefreshDatabase;

    public function test_the_stored_split_focus_selects_the_split_the_program_is_built_from(): void
    {
        $user = $this->member(trainingDays: 4, focus: SplitFocus::UpperFocus);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/plans/regenerate')
            ->assertCreated();

        $this->assertSame(
            ['Push Day', 'Pull Day', 'Upper Body Day', 'Legs Day'],
            $this->firstWeekWorkoutNames($user),
        );
    }

    public function test_a_member_stores_their_split_focus_on_their_profile_and_reads_it_back(): void
    {
        $user = $this->member(trainingDays: 4);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', ['split_focus' => 'lower_focus'])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('user.profile.split_focus', 'lower_focus');
    }

    public function test_an_emphasis_the_generator_has_no_split_for_is_rejected(): void
    {
        $user = $this->member(trainingDays: 4);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', ['split_focus' => 'arms_focus'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('split_focus');
    }

    public function test_a_member_who_has_never_chosen_an_emphasis_trains_balanced(): void
    {
        $user = $this->member(trainingDays: 4, focus: null);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/plans/regenerate')
            ->assertCreated();

        $this->assertSame(
            ['Push Day', 'Legs Day', 'Pull Day', 'Legs Day'],
            $this->firstWeekWorkoutNames($user),
        );
    }

    public function test_a_generated_program_names_the_split_focus_it_was_built_from(): void
    {
        $user = $this->member(trainingDays: 4, focus: SplitFocus::LowerFocus);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/plans/regenerate')
            ->assertCreated();

        $this->assertStringContainsString(
            'Lower Body focus',
            $user->fresh()->activeProgram->description,
        );
    }

    /**
     * The Split catalog is staff-maintained, and this feature triples the
     * combinations that have to stay populated — from 7 to 21. A member
     * whose pair has been emptied is told their request cannot be met,
     * rather than meeting a 500.
     */
    public function test_a_member_whose_split_is_missing_is_answered_not_failed(): void
    {
        $user = $this->member(trainingDays: 4, focus: SplitFocus::UpperFocus);

        WorkoutSplit::query()
            ->where('days_per_week', 4)
            ->where('focus', SplitFocus::UpperFocus)
            ->delete();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/plans/regenerate')
            ->assertStatus(422);
    }

    /**
     * The regression guard (ADR-0013). Gender decided the Split until
     * #143 — Female ⇒ lower focus — so two members who answered the
     * training questions identically were given different Programs. If
     * this fails, gender has found its way back into generation.
     */
    public function test_two_members_differing_only_in_gender_are_given_the_same_program(): void
    {
        $woman = $this->member(trainingDays: 4, focus: SplitFocus::UpperFocus, gender: Gender::Female);
        $man = $this->member(trainingDays: 4, focus: SplitFocus::UpperFocus, gender: Gender::Male);

        foreach ([$woman, $man] as $member) {
            $this->actingAs($member, 'sanctum')
                ->postJson('/api/plans/regenerate')
                ->assertCreated();
        }

        $this->assertSame(
            $this->firstWeekWorkoutNames($woman),
            $this->firstWeekWorkoutNames($man),
        );
    }

    /**
     * A member who has answered the training questions, ready to generate.
     */
    private function member(
        int $trainingDays,
        ?SplitFocus $focus = null,
        Gender $gender = Gender::Male,
    ): User {
        $this->seed(WorkoutSplitSeeder::class);
        $this->seedFullGymCatalog();

        $user = User::factory()->entitled()->create();
        $user->profile->update([
            'fitness_goal' => FitnessGoal::GeneralFitness,
            'training_experience' => TrainingExperience::Intermediate,
            'training_days_per_week' => $trainingDays,
            'workout_duration_minutes' => 60,
            'gender' => $gender,
            'split_focus' => $focus,
        ]);

        return $user->fresh();
    }

    /**
     * The day types the member's active Program opens with, in schedule
     * order — the Split, as the member reads it.
     *
     * @return list<string>
     */
    private function firstWeekWorkoutNames(User $user): array
    {
        return $user->fresh()->activeProgram
            ->workoutTemplates
            ->where('week_number', 1)
            ->sortBy('day_of_week')
            ->pluck('name')
            ->values()
            ->all();
    }
}
