<?php

namespace Tests\Feature;

use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Enums\TrainingExperience;
use App\Enums\UnitSystem;
use App\Enums\WorkoutSessionStatus;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\MovementPattern;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
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

    public function test_a_strength_member_topping_a_10_to_15_triceps_row_moves_up_one_step(): void
    {
        $member = $this->member(FitnessGoal::Strength);
        $triceps = $this->exercise('ELBOW_EXTENSION', 'CABLE');
        $template = $this->template($member, [[$triceps, 10, 15]]);

        $this->perform($member, $template, [[[30, 15], [30, 15], [30, 15]]]);

        $next = $this->nextSession($member, $template)[0];

        $this->assertSame('ready', $next['progression_status']);
        $this->assertEquals(32.5, $next['target_weight'], 'one 2.5 kg cable step');
    }

    /**
     * The matrix puts a general-fitness compound at 8–12; the member was
     * shown 6–10, so 10 reps is the top of what they were asked for.
     */
    public function test_a_general_fitness_member_topping_a_6_to_10_press_moves_up(): void
    {
        $member = $this->member(FitnessGoal::GeneralFitness);
        $press = $this->exercise('PRESS', 'BARBELL');
        $template = $this->template($member, [[$press, 6, 10]]);

        $this->perform($member, $template, [[[60, 10], [60, 10], [60, 10]]]);

        $next = $this->nextSession($member, $template)[0];

        $this->assertSame('ready', $next['progression_status']);
        $this->assertEquals(62.5, $next['target_weight']);
    }

    /**
     * A row generated before slot ranges stores 8–12. A muscle-gain compound
     * is 6–10 in the matrix, so 3×10 tops the matrix but not what the member
     * was shown: it holds, and the app says so.
     */
    public function test_a_legacy_8_to_12_row_is_judged_against_8_to_12(): void
    {
        $member = $this->member(FitnessGoal::MuscleGain);
        $press = $this->exercise('PRESS', 'BARBELL');
        $template = $this->template($member, [[$press, 8, 12]]);

        $this->perform($member, $template, [[[60, 10], [60, 10], [60, 10]]]);

        $next = $this->nextSession($member, $template)[0];

        $this->assertSame('working', $next['progression_status']);
        $this->assertEquals(60, $next['target_weight']);
    }

    /**
     * A row that stored no range showed the member the matrix range
     * (muscle-gain compound: 6–10), so that is what its sets are judged
     * against, even though the next row stores a range of its own.
     */
    public function test_a_row_storing_no_range_is_judged_against_the_matrix(): void
    {
        $member = $this->member(FitnessGoal::MuscleGain);
        $press = $this->exercise('PRESS', 'BARBELL');

        $session = WorkoutSession::factory()->create([
            'user_id' => $member->id,
            'workout_template_id' => null,
            'performed_at' => now(),
            'status' => WorkoutSessionStatus::Active,
        ]);
        $row = WorkoutSessionExercise::create([
            'workout_session_id' => $session->id,
            'exercise_id' => $press->id,
            'order' => 0,
            'target_sets' => 3,
            'min_target_reps' => null,
            'max_target_reps' => null,
            'rest_seconds' => 90,
        ]);

        $this->logAndComplete($member, $session->id, [[$row->id, $press->id, [[60, 10], [60, 10], [60, 10]]]]);

        $next = $this->nextSession($member, $this->template($member, [[$press, 8, 12]]))[0];

        $this->assertSame('ready', $next['progression_status']);
        $this->assertEquals(62.5, $next['target_weight']);
    }

    /**
     * One exercise on two rows: a heavy top-set row at 4–6 and a lighter
     * back-off row at 8–12. Each row's sets are judged against that row's
     * range, and the lighter back-off weight does not count against the top
     * set: topping both rows tops the session.
     */
    public function test_a_top_set_and_back_off_row_that_both_top_their_own_range_move_up(): void
    {
        $member = $this->member(FitnessGoal::MuscleGain);
        $press = $this->exercise('PRESS', 'BARBELL');
        $template = $this->template($member, [[$press, 4, 6], [$press, 8, 12]]);

        $this->perform($member, $template, [
            [[100, 6], [100, 6]],
            [[80, 12], [80, 12], [80, 12]],
        ]);

        [$topSet, $backOff] = $this->nextSession($member, $template);

        $this->assertSame('ready', $topSet['progression_status']);
        $this->assertSame('ready', $backOff['progression_status']);
        // One Working Weight per exercise: the session's most common weight, one step up.
        $this->assertEquals(82.5, $topSet['target_weight']);
        $this->assertEquals(82.5, $backOff['target_weight']);
    }

    public function test_a_back_off_row_short_of_its_own_top_holds_the_weight(): void
    {
        $member = $this->member(FitnessGoal::MuscleGain);
        $press = $this->exercise('PRESS', 'BARBELL');
        $template = $this->template($member, [[$press, 4, 6], [$press, 8, 12]]);

        $this->perform($member, $template, [
            [[100, 6], [100, 6]],
            [[80, 10], [80, 10], [80, 10]],
        ]);

        [$topSet, $backOff] = $this->nextSession($member, $template);

        // The top set's 6 reps are under the back-off's 8, and the back-off's
        // 10 reps are over the top set's 6: judged against the wrong row's
        // range, either would mislead. Against their own, the session is working.
        $this->assertSame('working', $topSet['progression_status']);
        $this->assertSame('working', $backOff['progression_status']);
        $this->assertEquals(80, $topSet['target_weight']);
        $this->assertEquals(80, $backOff['target_weight']);
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

        $this->logAndComplete($member, $session['id'], array_map(
            fn (array $exercise, array $sets) => [$exercise['session_exercise']['id'], $exercise['session_exercise']['exercise_id'], $sets],
            array_slice($session['exercises'], 0, count($setsPerRow)),
            $setsPerRow,
        ));
    }

    /**
     * @param  list<array{0: int, 1: int, 2: list<array{0: float|int, 1: int}>}>  $rows  [row id, exercise id, [weight, reps] pairs]
     */
    private function logAndComplete(User $member, int $sessionId, array $rows): void
    {
        foreach ($rows as [$rowId, $exerciseId, $sets]) {
            foreach ($sets as $setIndex => [$weight, $reps]) {
                $this->actingAs($member, 'sanctum')
                    ->postJson("/api/workout-sessions/{$sessionId}/sets", [
                        'workout_session_exercise_id' => $rowId,
                        'exercise_id' => $exerciseId,
                        'set_number' => $setIndex + 1,
                        'weight' => $weight,
                        'reps' => $reps,
                        'rest_seconds' => 90,
                    ])->assertCreated();
            }
        }

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/workout-sessions/{$sessionId}/complete")
            ->assertOk();

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
