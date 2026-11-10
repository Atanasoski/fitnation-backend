<?php

namespace Tests\Feature;

use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Enums\TrainingExperience;
use App\Enums\UnitSystem;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\MovementPattern;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\User;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Progression judges a working session's performance against each set's
 * Logged Range — the rep range its row prescribed when the set was logged —
 * and that one verdict drives both the Working Weight raise and
 * progression_status (ADR-0011).
 *
 * Each test drives the member's real flow: start a session from a template,
 * log sets, complete it, then start the next session and read its targets.
 */
class LoggedRangeProgressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_strength_member_mid_range_on_a_10_to_15_triceps_row_holds_the_weight(): void
    {
        $member = $this->member(FitnessGoal::Strength);
        $triceps = $this->exercise('ELBOW_EXTENSION', 'CABLE');
        $template = $this->template($member, [[$triceps, 10, 15]]);

        $this->perform($member, $template, [[[30, 10], [30, 10], [30, 10]]]);

        $next = $this->nextSession($member, $template)[0];

        $this->assertSame('working', $next['progression_status']);
        $this->assertEquals(30, $next['target_weight']);
    }

    // ---------------------------------------------------------------- helpers

    private function member(FitnessGoal $goal): User
    {
        $partner = Partner::factory()->create();
        $member = User::factory()->entitled()->create(['partner_id' => $partner->id]);

        $member->profile()->update([
            'fitness_goal' => $goal,
            'training_experience' => TrainingExperience::Intermediate,
            'gender' => Gender::Male,
            'weight' => 80,
            'unit_system' => UnitSystem::Metric,
        ]);

        return $member->fresh('profile');
    }

    private function exercise(string $pattern, string $equipment): Exercise
    {
        return Exercise::factory()->create([
            'movement_pattern_id' => MovementPattern::firstOrCreate(['code' => $pattern], ['name' => $pattern, 'display_order' => 1])->id,
            'equipment_type_id' => EquipmentType::firstOrCreate(['code' => $equipment], ['name' => $equipment, 'display_order' => 1])->id,
            'default_rest_sec' => 90,
        ]);
    }

    /**
     * @param  list<array{0: Exercise, 1: int, 2: int}>  $rows  [exercise, min reps, max reps]
     */
    private function template(User $member, array $rows, bool $isDeload = false): WorkoutTemplate
    {
        $template = WorkoutTemplate::create([
            'plan_id' => Plan::factory()->create(['user_id' => $member->id])->id,
            'name' => 'Day',
            'day_of_week' => 0,
            'week_number' => 1,
            'is_deload' => $isDeload,
            'order_index' => 0,
        ]);

        foreach ($rows as $order => [$exercise, $minReps, $maxReps]) {
            WorkoutTemplateExercise::create([
                'workout_template_id' => $template->id,
                'exercise_id' => $exercise->id,
                'order' => $order,
                'target_sets' => 3,
                'min_target_reps' => $minReps,
                'max_target_reps' => $maxReps,
                'target_weight' => 0,
                'rest_seconds' => 90,
            ]);
        }

        return $template;
    }

    /**
     * Start a session from the template, log each row's sets against that
     * row, and complete it.
     *
     * @param  list<list<array{0: float|int, 1: int}>>  $setsPerRow  per template row, [weight, reps] pairs
     */
    private function perform(User $member, WorkoutTemplate $template, array $setsPerRow): void
    {
        $session = $this->actingAs($member, 'sanctum')
            ->postJson('/api/workout-sessions/start', ['template_id' => $template->id])
            ->assertCreated()
            ->json('data');

        foreach ($setsPerRow as $index => $sets) {
            $row = $session['exercises'][$index]['session_exercise'];

            foreach ($sets as $setIndex => [$weight, $reps]) {
                $this->postJson("/api/workout-sessions/{$session['id']}/sets", [
                    'workout_session_exercise_id' => $row['id'],
                    'exercise_id' => $row['exercise_id'],
                    'set_number' => $setIndex + 1,
                    'weight' => $weight,
                    'reps' => $reps,
                    'rest_seconds' => 90,
                ])->assertCreated();
            }
        }

        $this->postJson("/api/workout-sessions/{$session['id']}/complete")->assertOk();

        $this->travel(1)->days();
    }

    /**
     * Start the next session from the template and read each row's targets.
     *
     * @return list<array<string, mixed>>
     */
    private function nextSession(User $member, WorkoutTemplate $template): array
    {
        $exercises = $this->actingAs($member, 'sanctum')
            ->postJson('/api/workout-sessions/start', ['template_id' => $template->id])
            ->assertCreated()
            ->json('data.exercises');

        return array_column($exercises, 'session_exercise');
    }
}
