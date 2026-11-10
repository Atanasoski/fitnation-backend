<?php

namespace Tests\Unit\Services;

use App\Enums\FitnessGoal;
use App\Enums\MovementClass;
use App\Services\WorkoutGenerator\PrescriptionCalculator;
use Tests\TestCase;

/**
 * A Prescription's rep range and rest for a user with no history: rep range
 * from the compound/isolation × fitness-goal matrix (by the movement class
 * it is given — no aliases), rest from the exercise's Default Rest scaled
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
        $prescription = $this->calculator->defaults(MovementClass::Compound, FitnessGoal::MuscleGain, 90);

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
        $prescription = $this->calculator->defaults(MovementClass::fromPattern('SHOULDER_RAISE'), FitnessGoal::Strength, 60);

        $this->assertSame(6, $prescription['min_target_reps']);
        $this->assertSame(10, $prescription['max_target_reps']);
        $this->assertSame(90, $prescription['rest_seconds']); // 60 × 1.5
    }

    /**
     * The class it is given decides both the matrix row and the rest floor,
     * whatever pattern rides along: a split squat prescribed as an isolation
     * gets the isolation range and floor (ADR-0010: the Slot's class wins).
     */
    public function test_the_given_class_decides_the_range_and_rest_floor_not_the_pattern(): void
    {
        $prescription = $this->calculator->defaults(MovementClass::Isolation, FitnessGoal::FatLoss, 60, 'LUNGE_SPLIT_SQUAT');

        $this->assertSame(10, $prescription['min_target_reps']);
        $this->assertSame(15, $prescription['max_target_reps']);
        $this->assertSame(30, $prescription['rest_seconds']); // 60 × 0.6 = 36 → isolation floor 30 → 30
    }

    public function test_a_fat_loss_compound_never_rests_under_60_seconds(): void
    {
        $prescription = $this->calculator->defaults(MovementClass::Compound, FitnessGoal::FatLoss, 90);

        $this->assertSame(60, $prescription['rest_seconds']); // 90 × 0.6 = 54 → floor 60
    }

    public function test_rest_is_capped_at_240_seconds(): void
    {
        $prescription = $this->calculator->defaults(MovementClass::Compound, FitnessGoal::Strength, 180);

        $this->assertSame(240, $prescription['rest_seconds']); // 180 × 1.5 = 270 → cap 240
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

        $prescription = $this->calculator->defaults(MovementClass::Compound, FitnessGoal::MuscleGain, 120, 'HINGE');

        $this->assertSame(5, $prescription['min_target_reps']);
        $this->assertSame(8, $prescription['max_target_reps']);
        $this->assertSame(120, $prescription['rest_seconds']); // matrix rest untouched
    }
}
