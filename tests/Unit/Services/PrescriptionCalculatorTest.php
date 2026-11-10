<?php

namespace Tests\Unit\Services;

use App\Enums\FitnessGoal;
use App\Services\WorkoutGenerator\PrescriptionCalculator;
use Tests\TestCase;

/**
 * A Prescription's rep range and rest for a user with no history: rep range
 * from the compound/isolation × fitness-goal matrix (by the exercise's raw
 * movement pattern — no aliases), rest from the exercise's Default Rest scaled
 * by the goal's multiplier, clamped with tier-aware floors and rounded to 15s.
 * A user's own history always outranks these defaults; that precedence lives
 * in the generator, not here.
 */
class PrescriptionCalculatorTest extends TestCase
{
    private PrescriptionCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PrescriptionCalculator;
    }

    public function test_a_compound_for_muscle_gain_gets_6_to_10_reps_and_its_default_rest(): void
    {
        $prescription = $this->calculator->defaults('PRESS', FitnessGoal::MuscleGain, 90);

        $this->assertSame(6, $prescription['min_target_reps']);
        $this->assertSame(10, $prescription['max_target_reps']);
        $this->assertSame(90, $prescription['rest_seconds']);
    }

    /**
     * A shoulder raise is an Isolation Movement, full stop (ADR-0009). Before
     * the alias removal it would have inherited PRESS's compound range.
     */
    public function test_a_shoulder_raise_prescribes_as_an_isolation_not_an_aliased_press(): void
    {
        $prescription = $this->calculator->defaults('SHOULDER_RAISE', FitnessGoal::Strength, 60);

        $this->assertSame(6, $prescription['min_target_reps']);
        $this->assertSame(10, $prescription['max_target_reps']);
        $this->assertSame(90, $prescription['rest_seconds']); // 60 × 1.5
    }

    public function test_a_fat_loss_compound_never_rests_under_60_seconds(): void
    {
        $prescription = $this->calculator->defaults('SQUAT', FitnessGoal::FatLoss, 90);

        $this->assertSame(60, $prescription['rest_seconds']); // 90 × 0.6 = 54 → floor 60
    }

    public function test_rest_is_capped_at_240_seconds(): void
    {
        $prescription = $this->calculator->defaults('HINGE', FitnessGoal::Strength, 180);

        $this->assertSame(240, $prescription['rest_seconds']); // 180 × 1.5 = 270 → cap 240
    }

    /**
     * An exercise with no curated Default Rest falls back to the goal's
     * default rest, taken as-is (no multiplier, no clamping).
     */
    public function test_missing_default_rest_falls_back_to_the_goal_default(): void
    {
        $prescription = $this->calculator->defaults('ROW', FitnessGoal::FatLoss, null);

        $this->assertSame(45, $prescription['rest_seconds']);
    }

    /**
     * A pattern listed in prescription_overrides beats the matrix for the
     * keys it provides; anything it leaves out keeps the matrix value.
     */
    public function test_a_pattern_override_beats_the_matrix(): void
    {
        config(['workout_generator.prescription_overrides' => [
            'HINGE' => ['min_target_reps' => 5, 'max_target_reps' => 8],
        ]]);

        $prescription = $this->calculator->defaults('HINGE', FitnessGoal::MuscleGain, 120);

        $this->assertSame(5, $prescription['min_target_reps']);
        $this->assertSame(8, $prescription['max_target_reps']);
        $this->assertSame(120, $prescription['rest_seconds']); // matrix rest untouched
    }
}
